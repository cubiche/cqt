<?php

/**
 * This file is part of the Cubiche package.
 *
 * Copyright (c) Cubiche
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Cubiche\Tools\CodeStyle;

use Symfony\CS\Console\Command\FixCommand;
use Symfony\CS\Fixer;
use Symfony\CS\Finder\DefaultFinder;
use Symfony\CS\Config\Config;
use Cubiche\Tools\GitUtils;

/**
 * CSFixCommand.
 *
 * @author Karel Osorio Ramírez <osorioramirez@gmail.com>
 */
class CSFixCommand extends FixCommand
{
    /**
     * @param Fixer $fixer
     */
    public function __construct(Fixer $fixer = null)
    {
        $finder = DefaultFinder::create();
        $finder->append($this->commitedFiles());

        parent::__construct($fixer, Config::create()->finder($finder));
    }

    /**
     * @return Generator
     */
    protected function commitedFiles()
    {
        foreach (GitUtils::commitedFiles() as $file) {
            yield new \SplFileInfo($file);
        }
    }
}
