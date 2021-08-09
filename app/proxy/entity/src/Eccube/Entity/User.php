<?php


/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * User
 *
 * @ORM\Table(name="m_user", uniqueConstraints={@ORM\UniqueConstraint(name="m_user_tel_unique", columns={"tel"}), @ORM\UniqueConstraint(name="m_user_login_id_unique", columns={"login_id"})})
 * @ORM\Entity
 */
class User
{
    /**
     * @var string
     *
     * @ORM\Column(name="user_cd", type="string", length=12, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="m_user_user_cd_seq", allocationSize=1, initialValue=1)
     */
    private $userCd;

    /**
     * @var string
     *
     * @ORM\Column(name="surname", type="string", length=30, nullable=false)
     */
    private $surname;

    /**
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=30, nullable=false)
     */
    private $lastname;

    /**
     * @var string|null
     *
     * @ORM\Column(name="surname_kana", type="string", length=50, nullable=true)
     */
    private $surnameKana;

    /**
     * @var string|null
     *
     * @ORM\Column(name="lastname_kana", type="string", length=50, nullable=true)
     */
    private $lastnameKana;

    /**
     * @var int|null
     *
     * @ORM\Column(name="gender", type="integer", nullable=true)
     */
    private $gender;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="birthday", type="date", nullable=true)
     */
    private $birthday;

    /**
     * @var string|null
     *
     * @ORM\Column(name="zipcode", type="string", length=8, nullable=true)
     */
    private $zipcode;

    /**
     * @var string|null
     *
     * @ORM\Column(name="addr_pref", type="string", length=12, nullable=true)
     */
    private $addrPref;

    /**
     * @var string|null
     *
     * @ORM\Column(name="addr_city", type="string", length=100, nullable=true)
     */
    private $addrCity;

    /**
     * @var string|null
     *
     * @ORM\Column(name="addr_district", type="string", length=150, nullable=true)
     */
    private $addrDistrict;

    /**
     * @var string|null
     *
     * @ORM\Column(name="addr_building", type="string", length=100, nullable=true)
     */
    private $addrBuilding;

    /**
     * @var string|null
     *
     * @ORM\Column(name="occupation", type="string", length=50, nullable=true)
     */
    private $occupation;

    /**
     * @var string|null
     *
     * @ORM\Column(name="tel", type="string", length=11, nullable=true)
     */
    private $tel;

    /**
     * @var int
     *
     * @ORM\Column(name="login_type", type="integer", nullable=false)
     */
    private $loginType;

    /**
     * @var string|null
     *
     * @ORM\Column(name="device_id", type="string", length=128, nullable=true)
     */
    private $deviceId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="notify_token", type="string", length=256, nullable=true)
     */
    private $notifyToken;

    /**
     * @var string
     *
     * @ORM\Column(name="login_id", type="string", length=128, nullable=false)
     */
    private $loginId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="password", type="string", length=50, nullable=true)
     */
    private $password;

    /**
     * @var string|null
     *
     * @ORM\Column(name="profile_avatar_url", type="string", length=200, nullable=true)
     */
    private $profileAvatarUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="salon_code", type="string", length=12, nullable=false)
     */
    private $salonCode;

    /**
     * @var int
     *
     * @ORM\Column(name="is_register_by_salon", type="integer", nullable=false)
     */
    private $isRegisterBySalon = '0';

    /**
     * @var int
     *
     * @ORM\Column(name="is_temp_off_by_salon", type="integer", nullable=false)
     */
    private $isTempOffBySalon = '0';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="temp_off_at", type="date", nullable=true)
     */
    private $tempOffAt;

    /**
     * @var int
     *
     * @ORM\Column(name="del_flg", type="integer", nullable=false)
     */
    private $delFlg = '0';

    /**
     * @var \Symfony\Component\Intl\DateFormatter\DateFormat\FullTransformer
     *
     * @ORM\Column(name="created_at", type="datetimetz", nullable=false, options={"default"="now()"}, length=6)
     */
    private $createdAt = 'now()';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetimetz", nullable=false, options={"default"="now()"}, length=6)
     */
    private $updatedAt = 'now()';

    /**
     * Set loginId.
     *
     * @param string|null $loginId
     *
     * @return User
     */
    public function setLoginId($loginId = null)
    {
        $this->loginId = $loginId;

        return $this;
    }

    /**
     * Get loginId.
     *
     * @return string|null
     */
    public function getLoginId()
    {
        return $this->loginId;
    }

    /**
     * Set salonCode.
     *
     * @param string|null $salonCode
     *
     * @return User
     */
    public function setSalonCode($salonCode = null)
    {
        $this->salonCode = $salonCode;

        return $this;
    }

    /**
     * Get salonCode.
     *
     * @return string|null
     */
    public function getSalonCode()
    {
        return $this->salonCode;
    }
}
