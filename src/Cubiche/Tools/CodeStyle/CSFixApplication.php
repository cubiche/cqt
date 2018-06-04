<?php

/**
 * This file is part of the Cubiche application.
 *
 * Copyright (c) Cubiche
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Cubiche\Tools\CodeStyle;

use Symfony\Component\Console\Application;

/**
 * CSFixCommand.
 *
 * @author Karel Osorio Ramírez <osorioramirez@gmail.com>
 */
class CSFixApplication extends Application
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('PHP CS Fixer Commit', '1.0.0');
        $this->add(new CSFixCommand());
    }
}
