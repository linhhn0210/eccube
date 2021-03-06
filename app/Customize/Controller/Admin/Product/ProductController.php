<?php


namespace Customize\Controller\Admin\Product;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\ExportCsvRow;
use Eccube\Entity\Master\CsvType;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Member;
use Eccube\Entity\Product;
use Eccube\Entity\ProductCategory;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductImage;
use Eccube\Entity\ProductStock;
use Eccube\Entity\ProductTag;
use Eccube\Entity\User;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Admin\ProductType;
use Eccube\Form\Type\Admin\SearchProductType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CategoryRepository;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Repository\Master\ProductStatusRepository;
use Eccube\Repository\ProductClassRepository;
use Eccube\Repository\ProductImageRepository;
use Customize\Repository\ProductRepository;
use Eccube\Repository\TagRepository;
use Eccube\Repository\TaxRuleRepository;
use Customize\Service\CsvExportService;
use Eccube\Util\CacheUtil;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class ProductController extends \Eccube\Controller\Admin\Product\ProductController
{
    /**
     * @var CsvExportService
     */
    protected $csvExportService;

    /**
     * @var ProductClassRepository
     */
    protected $productClassRepository;

    /**
     * @var ProductImageRepository
     */
    protected $productImageRepository;

    /**
     * @var TaxRuleRepository
     */
    protected $taxRuleRepository;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var ProductStatusRepository
     */
    protected $productStatusRepository;

    /**
     * @var TagRepository
     */
    protected $tagRepository;

    /**
     * ProductController constructor.
     *
     * @param CsvExportService $csvExportService
     * @param ProductClassRepository $productClassRepository
     * @param ProductImageRepository $productImageRepository
     * @param TaxRuleRepository $taxRuleRepository
     * @param CategoryRepository $categoryRepository
     * @param ProductRepository $productRepository
     * @param BaseInfoRepository $baseInfoRepository
     * @param PageMaxRepository $pageMaxRepository
     * @param ProductStatusRepository $productStatusRepository
     * @param TagRepository $tagRepository
     */
    public function __construct(
        CsvExportService $csvExportService,
        ProductClassRepository $productClassRepository,
        ProductImageRepository $productImageRepository,
        TaxRuleRepository $taxRuleRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        BaseInfoRepository $baseInfoRepository,
        PageMaxRepository $pageMaxRepository,
        ProductStatusRepository $productStatusRepository,
        TagRepository $tagRepository
    ) {
        parent::__construct(
            $csvExportService,
            $productClassRepository,
            $productImageRepository,
            $taxRuleRepository,
            $categoryRepository,
            $productRepository,
            $baseInfoRepository,
            $pageMaxRepository,
            $productStatusRepository,
            $tagRepository
        );
    }

    /**
     * @Route("/%eccube_admin_route%/product", name="admin_product")
     * @Route("/%eccube_admin_route%/product/page/{page_no}", requirements={"page_no" = "\d+"}, name="admin_product_page")
     * @Template("@admin/Product/index.twig")
     */
    public function index(Request $request, $page_no = null, Paginator $paginator)
    {
        $builder = $this->formFactory
            ->createBuilder(SearchProductType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_INDEX_INITIALIZE, $event);

        $searchForm = $builder->getForm();

        /**
         * ???????????????????????????, ??????????????????????????????.
         * - ??????????????????????????????
         * - ???????????????
         * - ??????????????????
         * ??????, ???????????????????????????????????? mtb_page_max????????????, ????????????????????????????????????.
         **/
        $page_count = $this->session->get('eccube.admin.product.search.page_count',
            $this->eccubeConfig->get('eccube_default_page_count'));

        $page_count_param = (int) $request->get('page_count');
        $pageMaxis = $this->pageMaxRepository->findAll();

        if ($page_count_param) {
            foreach ($pageMaxis as $pageMax) {
                if ($page_count_param == $pageMax->getName()) {
                    $page_count = $pageMax->getName();
                    $this->session->set('eccube.admin.product.search.page_count', $page_count);
                    break;
                }
            }
        }

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);

            if ($searchForm->isValid()) {
                /**
                 * ?????????????????????????????????, ?????????????????????????????????????????????.
                 * ????????????????????????????????????????????????????????????.
                 */
                $page_no = 1;
                $searchData = $searchForm->getData();

                // ????????????, ??????????????????????????????????????????.
                $this->session->set('eccube.admin.product.search', FormUtil::getViewData($searchForm));
                $this->session->set('eccube.admin.product.search.page_no', $page_no);
            } else {
                // ????????????????????????, ????????????????????????????????????????????????.
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $page_count,
                    'has_errors' => true,
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                /*
                 * ???????????????????????????????????????????????????????????????????????????, ????????????????????????????????????????????????.
                 */
                if ($page_no) {
                    // ????????????????????????????????????.
                    $this->session->set('eccube.admin.product.search.page_no', (int) $page_no);
                } else {
                    // ?????????????????????????????????.
                    $page_no = $this->session->get('eccube.admin.product.search.page_no', 1);
                }
                $viewData = $this->session->get('eccube.admin.product.search', []);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
            } else {
                /**
                 * ?????????????????????.
                 */
                $page_no = 1;
                // submit default value
                $viewData = FormUtil::getViewData($searchForm);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

                // ?????????????????????????????????, ???????????????????????????.
                $this->session->set('eccube.admin.product.search', $viewData);
                $this->session->set('eccube.admin.product.search.page_no', $page_no);
            }
        }

        $searchData['salon_cd'] = $this->getMemberSalonCd();
        $qb = $this->productRepository->getQueryBuilderBySearchDataForAdmin($searchData);

        $event = new EventArgs(
            [
                'qb' => $qb,
                'searchData' => $searchData,
            ],
            $request
        );

        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_INDEX_SEARCH, $event);

        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $page_count
        );

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false,
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/product/product/new", name="admin_product_product_new")
     * @Route("/%eccube_admin_route%/product/product/{id}/edit", requirements={"id" = "\d+"}, name="admin_product_product_edit")
     * @Template("@admin/Product/product.twig")
     */
    public function edit(Request $request, $id = null, RouterInterface $router, CacheUtil $cacheUtil)
    {
        $has_class = false;
        if (is_null($id)) {
            $Product = new Product();
            $salonCd = $this->getMemberSalonCd();
            $Product->setSalonCd($salonCd);
            $ProductClass = new ProductClass();
            $ProductStatus = $this->productStatusRepository->find(ProductStatus::DISPLAY_HIDE);
            $Product
                ->addProductClass($ProductClass)
                ->setStatus($ProductStatus);
            $ProductClass
                ->setVisible(true)
                ->setStockUnlimited(true)
                ->setProduct($Product);
            $ProductStock = new ProductStock();
            $ProductClass->setProductStock($ProductStock);
            $ProductStock->setProductClass($ProductClass);
        } else {
            $criteria = ['id' => $id];
            if ($this->getMemberAuthority() == 1) {
                $criteria['salon_cd'] = $this->getMemberSalonCd();
            }
            $Product = $this->productRepository->findOneBy($criteria);
            if (!$Product) {
                throw new NotFoundHttpException();
            }
            // ????????????????????????????????????????????????????????????????????????????????????
            $has_class = $Product->hasProductClass();
            if (!$has_class) {
                $ProductClasses = $Product->getProductClasses();
                foreach ($ProductClasses as $pc) {
                    if (!is_null($pc->getClassCategory1())) {
                        continue;
                    }
                    if ($pc->isVisible()) {
                        $ProductClass = $pc;
                        break;
                    }
                }
                if ($this->BaseInfo->isOptionProductTaxRule() && $ProductClass->getTaxRule()) {
                    $ProductClass->setTaxRate($ProductClass->getTaxRule()->getTaxRate());
                }
                $ProductStock = $ProductClass->getProductStock();
            }
        }

        $builder = $this->formFactory
            ->createBuilder(ProductType::class, $Product);

        // ???????????????????????????????????????????????????Form????????????
        if ($has_class) {
            $builder->remove('class');
        }

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Product' => $Product,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_EDIT_INITIALIZE, $event);

        $form = $builder->getForm();

        if (!$has_class) {
            $ProductClass->setStockUnlimited($ProductClass->isStockUnlimited());
            $form['class']->setData($ProductClass);
        }

        // ?????????????????????
        $images = [];
        $ProductImages = $Product->getProductImage();
        foreach ($ProductImages as $ProductImage) {
            $images[] = $ProductImage->getFileName();
        }
        $form['images']->setData($images);

        $categories = [];
        $ProductCategories = $Product->getProductCategories();
        foreach ($ProductCategories as $ProductCategory) {
            /* @var $ProductCategory \Eccube\Entity\ProductCategory */
            $categories[] = $ProductCategory->getCategory();
        }
        $form['Category']->setData($categories);

        $Tags = $Product->getTags();
        $form['Tag']->setData($Tags);

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                log_info('??????????????????', [$id]);
                $Product = $form->getData();

                if (!$has_class) {
                    $ProductClass = $form['class']->getData();

                    // ???????????????
                    if ($this->BaseInfo->isOptionProductTaxRule()) {
                        if ($ProductClass->getTaxRate() !== null) {
                            if ($ProductClass->getTaxRule()) {
                                $ProductClass->getTaxRule()->setTaxRate($ProductClass->getTaxRate());
                            } else {
                                $taxrule = $this->taxRuleRepository->newTaxRule();
                                $taxrule->setTaxRate($ProductClass->getTaxRate());
                                $taxrule->setApplyDate(new \DateTime());
                                $taxrule->setProduct($Product);
                                $taxrule->setProductClass($ProductClass);
                                $ProductClass->setTaxRule($taxrule);
                            }

                            $ProductClass->getTaxRule()->setTaxRate($ProductClass->getTaxRate());
                        } else {
                            if ($ProductClass->getTaxRule()) {
                                $this->taxRuleRepository->delete($ProductClass->getTaxRule());
                                $ProductClass->setTaxRule(null);
                            }
                        }
                    }
                    $this->entityManager->persist($ProductClass);

                    // ?????????????????????
                    if (!$ProductClass->isStockUnlimited()) {
                        $ProductStock->setStock($ProductClass->getStock());
                    } else {
                        // ?????????????????????null?????????
                        $ProductStock->setStock(null);
                    }
                    $this->entityManager->persist($ProductStock);
                }

                // ?????????????????????
                // ???????????????
                /* @var $Product \Eccube\Entity\Product */
                foreach ($Product->getProductCategories() as $ProductCategory) {
                    $Product->removeProductCategory($ProductCategory);
                    $this->entityManager->remove($ProductCategory);
                }
                $this->entityManager->persist($Product);
                $this->entityManager->flush();

                $count = 1;
                $Categories = $form->get('Category')->getData();
                $categoriesIdList = [];
                foreach ($Categories as $Category) {
                    foreach ($Category->getPath() as $ParentCategory) {
                        if (!isset($categoriesIdList[$ParentCategory->getId()])) {
                            $ProductCategory = $this->createProductCategory($Product, $ParentCategory, $count);
                            $this->entityManager->persist($ProductCategory);
                            $count++;
                            /* @var $Product \Eccube\Entity\Product */
                            $Product->addProductCategory($ProductCategory);
                            $categoriesIdList[$ParentCategory->getId()] = true;
                        }
                    }
                    if (!isset($categoriesIdList[$Category->getId()])) {
                        $ProductCategory = $this->createProductCategory($Product, $Category, $count);
                        $this->entityManager->persist($ProductCategory);
                        $count++;
                        /* @var $Product \Eccube\Entity\Product */
                        $Product->addProductCategory($ProductCategory);
                        $categoriesIdList[$ParentCategory->getId()] = true;
                    }
                }

                // ???????????????
                $add_images = $form->get('add_images')->getData();
                foreach ($add_images as $add_image) {
                    $ProductImage = new \Eccube\Entity\ProductImage();
                    $ProductImage
                        ->setFileName($add_image)
                        ->setProduct($Product)
                        ->setSortNo(1);
                    $Product->addProductImage($ProductImage);
                    $this->entityManager->persist($ProductImage);

                    // ??????
                    $file = new File($this->eccubeConfig['eccube_temp_image_dir'].'/'.$add_image);
                    $file->move($this->eccubeConfig['eccube_save_image_dir']);
                }

                // ???????????????
                $delete_images = $form->get('delete_images')->getData();
                foreach ($delete_images as $delete_image) {
                    $ProductImage = $this->productImageRepository
                        ->findOneBy(['file_name' => $delete_image]);

                    // ?????????????????????????????????????????????Entity?????????????????????
                    if ($ProductImage instanceof ProductImage) {
                        $Product->removeProductImage($ProductImage);
                        $this->entityManager->remove($ProductImage);
                    }
                    $this->entityManager->persist($Product);

                    // ??????
                    $fs = new Filesystem();
                    $fs->remove($this->eccubeConfig['eccube_save_image_dir'].'/'.$delete_image);
                }
                $this->entityManager->persist($Product);
                $this->entityManager->flush();

                $sortNos = $request->get('sort_no_images');
                if ($sortNos) {
                    foreach ($sortNos as $sortNo) {
                        list($filename, $sortNo_val) = explode('//', $sortNo);
                        $ProductImage = $this->productImageRepository
                            ->findOneBy([
                                'file_name' => $filename,
                                'Product' => $Product,
                            ]);
                        $ProductImage->setSortNo($sortNo_val);
                        $this->entityManager->persist($ProductImage);
                    }
                }
                $this->entityManager->flush();

                // ?????????????????????
                // ??????????????????????????????
                $ProductTags = $Product->getProductTag();
                foreach ($ProductTags as $ProductTag) {
                    $Product->removeProductTag($ProductTag);
                    $this->entityManager->remove($ProductTag);
                }

                // ?????????????????????
                $Tags = $form->get('Tag')->getData();
                foreach ($Tags as $Tag) {
                    $ProductTag = new ProductTag();
                    $ProductTag
                        ->setProduct($Product)
                        ->setTag($Tag);
                    $Product->addProductTag($ProductTag);
                    $this->entityManager->persist($ProductTag);
                }

                $Product->setUpdateDate(new \DateTime());
                $this->entityManager->flush();

                log_info('??????????????????', [$id]);

                $event = new EventArgs(
                    [
                        'form' => $form,
                        'Product' => $Product,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_EDIT_COMPLETE, $event);

                $this->addSuccess('admin.common.save_complete', 'admin');

                if ($returnLink = $form->get('return_link')->getData()) {
                    try {
                        // $returnLink???path????????????????????????. path???????????????????????????????????????????????????.
                        $pattern = '/^'.preg_quote($request->getBasePath(), '/').'/';
                        $returnLink = preg_replace($pattern, '', $returnLink);
                        $result = $router->match($returnLink);
                        // ???????????????????????????
                        $params = array_filter($result, function ($key) {
                            return 0 !== \strpos($key, '_');
                        }, ARRAY_FILTER_USE_KEY);

                        // path??????url????????????????????????????????????.
                        return $this->redirectToRoute($result['_route'], $params);
                    } catch (\Exception $e) {
                        // ?????????????????????????????????????????????????????????.
                        log_warning('URL???????????????????????????');
                    }
                }

                $cacheUtil->clearDoctrineCache();

                return $this->redirectToRoute('admin_product_product_edit', ['id' => $Product->getId()]);
            }
        }

        // ?????????????????????
        $builder = $this->formFactory
            ->createBuilder(SearchProductType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Product' => $Product,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_EDIT_SEARCH, $event);

        $searchForm = $builder->getForm();

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);
        }

        // Get Tags
        $TagsList = $this->tagRepository->getList();

        // ??????????????????????????????????????????????????????????????????
        $TopCategories = $this->categoryRepository->getList(null);
        $ChoicedCategoryIds = array_map(function ($Category) {
            return $Category->getId();
        }, $form->get('Category')->getData());

        return [
            'Product' => $Product,
            'Tags' => $Tags,
            'TagsList' => $TagsList,
            'form' => $form->createView(),
            'searchForm' => $searchForm->createView(),
            'has_class' => $has_class,
            'id' => $id,
            'TopCategories' => $TopCategories,
            'ChoicedCategoryIds' => $ChoicedCategoryIds,
        ];
    }

    /**
     * ProductCategory??????
     *
     * @param \Eccube\Entity\Product $Product
     * @param \Eccube\Entity\Category $Category
     * @param integer $count
     *
     * @return \Eccube\Entity\ProductCategory
     */
    private function createProductCategory($Product, $Category, $count)
    {
        $ProductCategory = new ProductCategory();
        $ProductCategory->setProduct($Product);
        $ProductCategory->setProductId($Product->getId());
        $ProductCategory->setCategory($Category);
        $ProductCategory->setCategoryId($Category->getId());

        return $ProductCategory;
    }

    public function getMemberSalonCd()
    {
        $loginId = $this->getUser()->getUsername();
        /** @var Member $member */
        $member = $this->entityManager->getRepository('Eccube\Entity\Member')->findOneBy(['login_id' => $loginId]);
        $authorityId = $member->getAuthority()->getId();
        if ($authorityId === 0) {
            return null;
        }
        /** @var User $user */
        $user = $this->entityManager->getRepository('Eccube\Entity\User')->findOneBy(['loginId' => $loginId]);
        return $user->getSalonCode();
    }

    public function getMemberAuthority()
    {
        $loginId = $this->getUser()->getUsername();
        /** @var Member $member */
        $member = $this->entityManager->getRepository('Eccube\Entity\Member')->findOneBy(['login_id' => $loginId]);
        return $member->getAuthority()->getId();
    }

    /**
     * ??????CSV?????????.
     *
     * @Route("/%eccube_admin_route%/product/export", name="admin_product_export")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     */
    public function export(Request $request)
    {
        // ????????????????????????????????????.
        set_time_limit(0);

        // sql logger??????????????????.
        $em = $this->entityManager;
        $em->getConfiguration()->setSQLLogger(null);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($request) {
            // CSV????????????????????????.
            $this->csvExportService->initCsvType(CsvType::CSV_TYPE_PRODUCT);

            // ?????????????????????.
            $this->csvExportService->exportHeader();

            // ??????????????????????????????????????????????????????.
            $qb = $this->csvExportService
                ->getProductQueryBuilder($request);

            // Get stock status
            $isOutOfStock = 0;
            $session = $request->getSession();
            if ($session->has('eccube.admin.product.search')) {
                $searchData = $session->get('eccube.admin.product.search', []);
                if (isset($searchData['stock_status']) && $searchData['stock_status'] === 0) {
                    $isOutOfStock = 1;
                }
            }

            // join???????????????iterate?????????????????????, select??????distinct??????.
            // http://qiita.com/suin/items/2b1e98105fa3ef89beb7
            // distinct???mysql???pgsql????????????????????????.
            // http://uedatakeshi.blogspot.jp/2010/04/distinct-oeder-by-postgresmysql.html
            $qb->resetDQLPart('select')
                ->resetDQLPart('orderBy')
                ->orderBy('p.update_date', 'DESC');

            if ($isOutOfStock) {
                $qb->select('p, pc')
                    ->distinct();
            } else {
                $qb->select('p')
                    ->distinct();
            }
            // ?????????????????????.
            $this->csvExportService->setExportQueryBuilder($qb);

            $this->csvExportService->exportData(function ($entity, CsvExportService $csvService) use ($request) {
                $Csvs = $csvService->getCsvs();

                /** @var $Product \Eccube\Entity\Product */
                $Product = $entity;

                /** @var $ProductClasses \Eccube\Entity\ProductClass[] */
                $ProductClasses = $Product->getProductClasses();

                foreach ($ProductClasses as $ProductClass) {
                    $ExportCsvRow = new ExportCsvRow();

                    // CSV?????????????????????????????????????????????.
                    foreach ($Csvs as $Csv) {
                        // ????????????????????????.
                        $ExportCsvRow->setData($csvService->getData($Csv, $Product));
                        if ($ExportCsvRow->isDataNull()) {
                            // ???????????????????????????.
                            $ExportCsvRow->setData($csvService->getData($Csv, $ProductClass));
                        }

                        $event = new EventArgs(
                            [
                                'csvService' => $csvService,
                                'Csv' => $Csv,
                                'ProductClass' => $ProductClass,
                                'ExportCsvRow' => $ExportCsvRow,
                            ],
                            $request
                        );
                        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_CSV_EXPORT, $event);

                        $ExportCsvRow->pushData();
                    }

                    // $row[] = number_format(memory_get_usage(true));
                    // ??????.
                    $csvService->fputcsv($ExportCsvRow->getRow());
                }
            });
        });

        $now = new \DateTime();
        $filename = 'product_'.$now->format('YmdHis').'.csv';
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->send();

        log_info('??????CSV?????????????????????', [$filename]);

        return $response;
    }
}