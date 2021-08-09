<?php


namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;
use Eccube\Entity\Product;

/**
 * @EntityExtension("Eccube\Entity\Product")
 */
trait ProductTrait
{
    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $salon_cd;

    /**
     * Set salonCd.
     *
     * @param string|null $salonCd
     *
     * @return Product
     */
    public function setSalonCd($salonCd = null)
    {
        $this->salon_cd = $salonCd;

        return $this;
    }

    /**
     * Get salonCd.
     *
     * @return string|null
     */
    public function getSalonCd()
    {
        return $this->salon_cd;
    }

}
