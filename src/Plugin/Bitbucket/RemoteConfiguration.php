<?php

/*
 * Copyright (c) SLONline
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace SLONline\Packages\Plugin\Bitbucket;

use Doctrine\ORM\Mapping as ORM;
use Terramar\Packages\Entity\Remote;

/**
 * @ORM\Entity
 * @ORM\Table(name="packages_bitbucket_remotes")
 */
class RemoteConfiguration
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="enabled", type="boolean")
     */
    private $enabled = false;

    /**
     * @ORM\Column(name="key", type="string")
     */
    private $key;

    /**
     * @ORM\Column(name="secret_key", type="string")
     */
    private $secret_key;

	/**
	 * @ORM\Column(name="account", type="string")
	 */
    private $account;

    /**
     * @ORM\ManyToOne(targetEntity="Terramar\Packages\Entity\Remote")
     * @ORM\JoinColumn(name="remote_id", referencedColumnName="id")
     */
    private $remote;

    /**
     * @param mixed $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool)$enabled;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Remote
     */
    public function getRemote()
    {
        return $this->remote;
    }

    /**
     * @param Remote $remote
     */
    public function setRemote(Remote $remote)
    {
        $this->remote = $remote;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $key
     */
    public function setKey($key)
    {
        $this->key = (string)$key;
    }

    /**
     * @return mixed
     */
    public function getSecretKey()
    {
        return $this->secret_key;
    }

    /**
     * @param mixed $secret_key
     */
    public function setSecretKey($secret_key)
    {
        $this->secret_key = (string)$secret_key;
    }

	/**
	 * @return mixed
	 */
    public function getAccount()
    {
    	return $this->account;
    }

	/**
	 * @param mixed $account
	 */
    public function setAccount($account)
    {
    	$this->account = (string)$account;
    }
}
