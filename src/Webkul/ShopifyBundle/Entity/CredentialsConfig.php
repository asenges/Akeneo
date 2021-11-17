<?php

namespace Webkul\ShopifyBundle\Entity;

/**
 * CredentialsConfig
 */
class CredentialsConfig
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var bool
     */
    private $active;

    /**
     * @var string
     */
    private $extras;

    private $apiVersion;
    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    
    /**
     * Set apiKey.
     *
     * @param string $apiKey
     *
     * @return CredentialsConfig
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Get apiKey.
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Set active.
     *
     * @param bool $active
     *
     * @return CredentialsConfig
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active.
     *
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set active.
     *
     * @param bool $active
     *
     * @return CredentialsConfig
     */
    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = $apiVersion;

        return $this;
    }

    /**
     * Get active.
     *
     * @return bool
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Change the active value vice-versa
     */
    public function activation()
    {
        $this->active = !$this->active;

        return $this;
    }

    /**
     * Set extras.
     *
     * @param string $extras
     *
     * @return CredentialsConfig
     */
    public function setExtras($extras)
    {
        $this->extras = $extras;

        return $this;
    }

    /**
     * Get extras.
     *
     * @return string
     */
    public function getExtras()
    {
        return $this->extras;
    }
    /**
     * @var string
     */
    private $resources;


    /**
     * Set resources.
     *
     * @param string $resources
     *
     * @return CredentialsConfig
     */
    public function setResources($resources)
    {
        $this->resources = $resources;

        return $this;
    }

    /**
     * Get resources.
     *
     * @return string
     */
    public function getResources()
    {
        return $this->resources;
    }
    /**
     * @var string
     */
    private $apiPassword;


    /**
     * Set apiPassword.
     *
     * @param string $apiPassword
     *
     * @return CredentialsConfig
     */
    public function setApiPassword($apiPassword)
    {
        $this->apiPassword = $apiPassword;

        return $this;
    }

    /**
     * Get apiPassword.
     *
     * @return string
     */
    public function getApiPassword()
    {
        return $this->apiPassword;
    }
    /**
     * @var string
     */
    private $shopUrl;


    /**
     * Set shopUrl.
     *
     * @param string $shopUrl
     *
     * @return CredentialsConfig
     */
    public function setShopUrl($shopUrl)
    {
        $this->shopUrl = $shopUrl;

        return $this;
    }

    /**
     * Get shopUrl.
     *
     * @return string
     */
    public function getShopUrl()
    {
        return $this->shopUrl;
    }
    /**
     * @var bool
     */
    private $defaultSet = 0;


    /**
     * Set defaultSet.
     *
     * @param bool $defaultSet
     *
     * @return CredentialsConfig
     */
    public function setDefaultSet($defaultSet)
    {
        $this->defaultSet = $defaultSet;

        return $this;
    }

    /**
     * Get defaultSet.
     *
     * @return bool
     */
    public function getDefaultSet()
    {
        return $this->defaultSet;
    }
}
