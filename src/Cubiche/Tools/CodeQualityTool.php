<?php

/**
 * This file is part of the Cubiche package.
 *
 * Copyright (c) Cubiche
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Cubiche\Tools;

use Composer\Script\Event;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * CodeQualityTool.
 *
 * @author Karel Osorio Ramírez <osorioramirez@gmail.com>
 * @author Ivannis Suárez Jérez <ivannis.suarez@gmail.com>
 */
class CodeQualityTool extends Application
{
    const CONFIG_FILE = 'quality.yml';

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var array
     */
    protected $config;

    /**
     * CodeQualityTool constructor.
     */
    public function __construct()
    {
        parent::__construct('Cubiche Code Quality Tool', '1.0.0');

        $this->config = file_exists(self::CONFIG_FILE) ? Yaml::parse(file_get_contents(self::CONFIG_FILE)) : array();
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $output->writeln(
            \sprintf(
                '<fg=white;options=bold;bg=blue>%s %s</fg=white;options=bold;bg=blue>',
                $this->getName(),
                $this->getVersion()
            )
        );

        $output->writeln('<info>Check composer</info>');
        $this->checkComposer();

        $output->writeln('<info>Running PHPLint</info>');
        if (!$this->phpLint()) {
            throw new \Exception('There are some PHP syntax errors!');
        }

        $output->writeln('<info>Checking code style</info>');
        if (!$this->codeStyle()) {
            throw new \Exception(sprintf('There are coding standards violations!'));
        }

        $output->writeln('<info>Checking code style with PHPCS</info>');
        if (!$this->codeStylePsr()) {
            throw new \Exception(sprintf('There are PHPCS coding standards violations!'));
        }

        $output->writeln('<info>Checking code mess with PHPMD</info>');
        if (!$this->phPmd()) {
            throw new \Exception(sprintf('There are PHPMD violations!'));
        }

        $output->writeln('<info>Running unit tests</info>');
        if (!$this->unitTests()) {
            throw new \Exception('Fix the unit tests!');
        }

        $output->writeln('<info>Good job dude!</info>');
    }

    /**
     * Check composer.json and composer.lock files
     */
    private function checkComposer()
    {
        $composerJsonDetected = false;
        $composerLockDetected = false;
        foreach (GitUtils::extractCommitedFiles() as $file) {
            if ($file === 'composer.json') {
                $composerJsonDetected = true;
            }
            if ($file === 'composer.lock') {
                $composerLockDetected = true;
            }
        }
        if ($composerJsonDetected && !$composerLockDetected) {
            $this->output->writeln(
                '<bg=yellow;fg=black>
                 composer.lock must be commited if composer.json is modified!
                 </bg=yellow;fg=black>'
            );
        }
    }

    /**
     * @return bool
     */
    protected function phpLint()
    {
        $needle = '/(\.php)|(\.inc)$/';
        $succeed = true;
        $config = $this->getConfig('phplint', array(
            'triggered_by' => 'php'
        ));

        foreach (GitUtils::commitedFiles($needle) as $file) {
            $processBuilder = new ProcessBuilder(array($config['triggered_by'], '-l', $file));
            $process = $processBuilder->getProcess();
            $process->run();
            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getErrorOutput())));
                $succeed = false;
            }
        }

        return $succeed;
    }

    /**
     * @return bool
     */
    protected function phPmd()
    {
        $succeed = true;
        $config = $this->getConfig('phpmd', array(
            'ruleset' => 'controversial',
            'triggered_by' => 'php'
        ));

        $rootPath = realpath(getcwd());
        foreach (GitUtils::commitedFiles() as $file) {
            $processBuilder = new ProcessBuilder(
                array($config['triggered_by'], 'bin/phpmd', $file, 'text', implode(',', $config['ruleset']))
            );
            $processBuilder->setWorkingDirectory($rootPath);
            $process = $processBuilder->getProcess();
            $process->run();
            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getErrorOutput())));
                $this->output->writeln(sprintf('<info>%s</info>', trim($process->getOutput())));
                $succeed = false;
            }
        }

        return $succeed;
    }

    /**
     * @param string $suiteName
     *
     * @return bool
     */
    protected function unitTests($suiteName = null)
    {
        $config = $this->getConfig('test', array(
            'suites' => array()
        ));

        if (count($config['suites']) > 0) {
            foreach ($config['suites'] as $name => $suite) {
                if ($suiteName !== null && $name !== $suiteName) {
                    continue;
                }

                $trigger = isset($suite['triggered_by']) ? $suite['triggered_by'] : 'php';

                $configFile = '.atoum.php';
                if (isset($suite['config_file']) && $suite['config_file'] !== null) {
                    $configFile = $suite['config_file'];
                }

                $bootstrapFile = '.bootstrap.atoum.php';
                if (isset($suite['bootstrap_file']) && $suite['bootstrap_file'] !== null) {
                    $bootstrapFile = $suite['bootstrap_file'];
                }

                $arguments = array(
                    $trigger,
                    'bin/atoum',
                    '-c',
                    $configFile,
                    '-bf',
                    $bootstrapFile
                );

                if (isset($suite['directories']) && $suite['directories'] !== null) {
                    $arguments[] = '-d';
                    $arguments[] = implode(" ", $suite['directories']);
                }

                $processBuilder = new ProcessBuilder($arguments);
                $processBuilder->setWorkingDirectory(getcwd());
                $processBuilder->setTimeout(null);

                $test = $processBuilder->getProcess();
                $test->run(
                    function ($type, $buffer) {
                        $this->output->write($buffer);
                    }
                );

                if (!$test->isSuccessful()) {
                    return false;
                }
            }

            return true;
        } else {
            $this->output->writeln('<comment>There is no tests configuration suites</comment>');

            return true;
        }
    }

    /**
     * @param string $directory
     *
     * @return bool
     */
    protected function codeStyle($directory = null)
    {
        $succeed = true;
        $config = $this->getConfig('phpcsfixer', array(
            'fixers' => [
                '-psr0','eof_ending','indentation','linefeed','lowercase_keywords','trailing_spaces',
                'short_tag','php_closing_tag','extra_empty_lines','elseif','function_declaration'
            ],
            'triggered_by' => 'php'
        ));

        $fixers = implode(',', $config['fixers']);
        if ($directory !== null && is_dir($directory)) {
            $processBuilder = new ProcessBuilder(array(
                $config['triggered_by'],
                'bin/php-cs-fixer',
                '--dry-run',
                '--verbose',
                'fix',
                $directory,
                '--fixers='.$fixers,
            ));

            $processBuilder->setWorkingDirectory(getcwd());
            $phpCsFixer = $processBuilder->getProcess();
            $phpCsFixer->run();

            if (!$phpCsFixer->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($phpCsFixer->getOutput())));

                return false;
            }
        } else {
            foreach (GitUtils::commitedFiles() as $file) {
                $processBuilder = new ProcessBuilder(array(
                    $config['triggered_by'],
                    'bin/php-cs-fixer',
                    '--dry-run',
                    '--verbose',
                    'fix',
                    $file,
                    '--fixers='.$fixers,
                ));

                $processBuilder->setWorkingDirectory(getcwd());
                $phpCsFixer = $processBuilder->getProcess();
                $phpCsFixer->run();

                if (!$phpCsFixer->isSuccessful()) {
                    $this->output->writeln(sprintf('<error>%s</error>', trim($phpCsFixer->getOutput())));
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    /**
     * @param string $directory
     *
     * @return bool
     */
    protected function codeStylePsr($directory = null)
    {
        $succeed = true;
        $config = $this->getConfig('phpcs', array(
            'standard' => 'PSR2',
            'triggered_by' => 'php'
        ));

        if ($directory !== null && is_dir($directory)) {
            $processBuilder = new ProcessBuilder(
                array($config['triggered_by'], 'bin/phpcs', '--standard='.$config['standard'], $directory)
            );

            $processBuilder->setWorkingDirectory(getcwd());
            $phpCsFixer = $processBuilder->getProcess();
            $phpCsFixer->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (!$phpCsFixer->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($phpCsFixer->getOutput())));

                return false;
            }
        } else {
            foreach (GitUtils::commitedFiles() as $file) {
                $processBuilder = new ProcessBuilder(
                    array($config['triggered_by'], 'bin/phpcs', '--standard='.$config['standard'], $file)
                );

                $processBuilder->setWorkingDirectory(getcwd());
                $phpCsFixer = $processBuilder->getProcess();
                $phpCsFixer->run(function ($type, $buffer) {
                    $this->output->write($buffer);
                });

                if (!$phpCsFixer->isSuccessful()) {
                    $this->output->writeln(sprintf('<error>%s</error>', trim($phpCsFixer->getOutput())));
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    /**
     * @param Event $event
     */
    public static function checkHooks(Event $event)
    {
        $io = $event->getIO();

        if (!is_dir(getcwd().'/.git/hooks')) {
            $io->write('<error>The .git/hooks directory does not exist, execute git init</error>');

            return;
        }

        $gitPath = getcwd().'/.git/hooks/pre-commit';
        $docPath = getcwd().'/bin/pre-commit';
        $gitHook = @file_get_contents($gitPath);
        $docHook = @file_get_contents($docPath);
        if ($gitHook !== $docHook) {
            self::createSymlink($gitPath, $docPath);
        }
    }

    /**
     * @param string $symlinkTarget
     * @param string $symlinkName
     *
     * @throws \Exception
     */
    private static function createSymlink($symlinkTarget, $symlinkName)
    {
        $processBuilder = new ProcessBuilder(array('rm', '-rf', $symlinkTarget));
        $process = $processBuilder->getProcess();
        $process->run();

        if (symlink($symlinkName, $symlinkTarget) === false) {
            throw new \Exception('Error occured when trying to create a symlink.');
        }
    }

    /**
     * @param string $task
     * @param array  $defaults
     *
     * @return array|mixed
     */
    private function getConfig($task, array $defaults = array())
    {
        return isset($this->config[$task]) ? $this->config[$task] : $defaults;
    }
}
