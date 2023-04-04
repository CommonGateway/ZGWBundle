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
    }

    public function install()
    {
    
    }

    public function update()
    {
    
    }

    public function uninstall()
    {
        // Do some cleanup
    }
}
