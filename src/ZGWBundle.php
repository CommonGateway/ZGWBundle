<?php

// src/ZGWBundle.php
namespace CommonGateway\ZGWBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * This main class makes the bundle findable and usable.
 *
 * @author  Conduction BV <info@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Bundle
 */
class ZGWBundle extends Bundle
{
    
    
    /**
     * Returns the path the bundle is in.
     *
     * @return string
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
        
    }//end getPath()
    
    
}//end class
