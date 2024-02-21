<?php

// src/Service/InstallationService.php
namespace CommonGateway\ZGWBundle\Service;

use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;

class InstallationService implements InstallerInterface
{

    private EntityManagerInterface $entityManager;


    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

    }//end __construct()


    public function install()
    {

    }//end install()


    public function update()
    {

    }//end update()


    public function uninstall()
    {
        // Do some cleanup
    }//end uninstall()


}//end class
