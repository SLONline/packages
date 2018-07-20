<?php

/*
 * Copyright (c) SLONline
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace SLONline\Packages\Plugin\Bitbucket;


use Bitbucket\API\Repositories;
use Bitbucket\API\Http\Listener\OAuthListener;
use Doctrine\ORM\EntityManager;
use Nice\Router\UrlGeneratorInterface;
use Terramar\Packages\Entity\Package;
use Terramar\Packages\Entity\Remote;
use Terramar\Packages\Helper\SyncAdapterInterface;

class SyncAdapter implements SyncAdapterInterface
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * @var \Nice\Router\UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * Constructor.
     *
     * @param EntityManager $entityManager
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(EntityManager $entityManager, UrlGeneratorInterface $urlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param Remote $remote
     *
     * @return bool
     */
    public function supports(Remote $remote)
    {
        return $remote->getAdapter() === $this->getName();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Bitbucket';
    }

    /**
     * @param Remote $remote
     *
     * @return Package[]
     */
    public function synchronizePackages(Remote $remote)
    {
        $existingPackages = $this->entityManager->getRepository('Terramar\Packages\Entity\Package')->findBy(['remote' => $remote]);

        $projects = $this->getAllProjects($remote);

        $packages = [];
        foreach ($projects as $project) {
        	$links_clone = [];
	        foreach ($project['links']['clone'] as $link_clone)
	        {
	        	$links_clone[$link_clone['name']] = $link_clone['href'];
	        }

            $package = $this->getExistingPackage($existingPackages, $project['uuid']);
            if ($package === null) {
                $package = new Package();
                $package->setExternalId($project['uuid']);
                $package->setRemote($remote);
            }
            $package->setName($project['name']);
            $package->setDescription($project['description']);
            $package->setFqn($project['slug']);
            $package->setWebUrl($project['links']['html']['href']);
            $package->setSshUrl($links_clone['ssh']);
            $packages[] = $package;
        }

        $removed = array_diff($existingPackages, $packages);
        foreach ($removed as $package) {
            $this->entityManager->remove($package);
        }

        return $packages;
    }

    private function getAllProjects(Remote $remote)
    {
	    $config = $this->getRemoteConfig($remote);

	    $oauth_params = array(
		    'oauth_consumer_key'      => $config->getKey(),
		    'oauth_consumer_secret'   => $config->getSecretKey()
	    );

	    $client = new Repositories();
	    $client->getClient()->addListener(
		    new OAuthListener($oauth_params)
	    );

        $projects = [];
        $page = 1;
        while (true) {
            $response = $client->all($config->getAccount(), [
                'page'     => $page,
                'per_page' => 1,
            ]);
            $projects = array_merge($projects, ResponseMediator::getContent($response)['values']);
            $pageInfo = ResponseMediator::getPagination($response);
            if (!isset($pageInfo['next'])) {
                break;
            }

            ++$page;
        }

        return $projects;
    }

    /**
     * @param Remote $remote
     *
     * @return RemoteConfiguration
     */
    private function getRemoteConfig(Remote $remote)
    {
        return $this->entityManager->getRepository('SLONline\Packages\Plugin\Bitbucket\RemoteConfiguration')->findOneBy(['remote' => $remote]);
    }

    /**
     * @param $existingPackages []Package
     * @param $bitbucketID
     * @return Package|null
     */
    private function getExistingPackage($existingPackages, $bitbucketID)
    {
        $res = array_filter($existingPackages, function (Package $package) use ($bitbucketID) {
            return (string)$package->getExternalId() === (string)$bitbucketID;
        });
        if (count($res) === 0) {
            return null;
        }
        return array_shift($res);
    }


    /**
     * Enable a Bitbucket webhook for the given Package.
     *
     * @param Package $package
     *
     * @return bool
     */
    public function enableHook(Package $package)
    {
        $config = $this->getConfig($package);

        if ($config->isEnabled()) {
            return true;
        }

        try {
	        $remote_config = $this->getRemoteConfig($package->getRemote());

	        $oauth_params = array(
		        'oauth_consumer_key'      => $remote_config->getKey(),
		        'oauth_consumer_secret'   => $remote_config->getSecretKey()
	        );

	        $hooks = new Repositories\Hooks();
	        $hooks->getClient()->addListener(
		        new OAuthListener($oauth_params)
	        );

	        $response = $hooks->create($remote_config->getAccount(), $package->getFqn(),
		        [
			        'description' => 'web-private-packages',
			        'url' => $this->urlGenerator->generate('webhook_receive', ['id' => $package->getId()], true),
			        'active' => true,
			        'skip_cert_verification' => 0,
			        'events' => [
				        'repo:push'
			        ]
		        ]);

	        $hook = ResponseMediator::getContent($response);

            $package->setHookExternalId($hook['uuid']);
            $config->setEnabled(true);

            return true;

        } catch (\Exception $e) {
            // TODO: Log the exception
	        echo $e->getMessage();exit;
            return false;
        }
    }

    private function getConfig(Package $package)
    {
        return $this->entityManager->getRepository('SLONline\Packages\Plugin\Bitbucket\PackageConfiguration')->findOneBy(['package' => $package]);
    }

    /**
     * Disable a Bitbucket webhook for the given Package.
     *
     * @param Package $package
     *
     * @return bool
     */
    public function disableHook(Package $package)
    {
        $config = $this->getConfig($package);
        if (!$config->isEnabled()) {
            return true;
        }

        try {
            if ($package->getHookExternalId()) {
	            $remote_config = $this->getRemoteConfig($package->getRemote());

	            $oauth_params = array(
		            'oauth_consumer_key'      => $remote_config->getKey(),
		            'oauth_consumer_secret'   => $remote_config->getSecretKey()
	            );

	            $hooks = new Repositories\Hooks();
	            $hooks->getClient()->addListener(
		            new OAuthListener($oauth_params)
	            );

	            $hooks->delete($remote_config->getAccount(), $package->getFqn(), \str_replace(['{', '}'],'',$package->getHookExternalId()));
            }

            $package->setHookExternalId('');
            $config->setEnabled(false);

            return true;

        } catch (\Exception $e) {
            // TODO: Log the exception
            $package->setHookExternalId('');
            $config->setEnabled(false);

            return false;
        }
    }
}
