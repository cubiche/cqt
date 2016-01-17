<?php

/**
 * This file is part of the Cubiche/cqt project.
 */
namespace Cubiche\Tools;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Application;
use Composer\Script\Event;

/**
 * CodeQualityTool.
 *
 * @author Karel Osorio Ramírez <osorioramirez@gmail.com>
 * @author Ivannis Suárez Jérez <ivannis.suarez@gmail.com>
 */
class CodeQualityTool extends Application
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    public function __construct()
    {
        parent::__construct('Cubiche Code Quality Tool', '1.0.0');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Application::doRun()
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
     * @param array $files
     *
     * @return bool
     */
    protected function phpLint()
    {
        $needle = '/(\.php)|(\.inc)$/';
        $succeed = true;
        foreach (GitUtils::commitedFiles($needle) as $file) {
            $processBuilder = new ProcessBuilder(array('php', '-l', $file));
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
        $rootPath = realpath(getcwd());
        foreach (GitUtils::commitedFiles() as $file) {
            $processBuilder = new ProcessBuilder(['php', 'bin/phpmd', $file, 'text', 'controversial']);
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
     * @return bool
     */
    private function unitTests()
    {
        $processBuilder = new ProcessBuilder(array('php', 'bin/phpunit', '--colors'));
        $processBuilder->setWorkingDirectory(getcwd());
        $processBuilder->setTimeout(3600);
        $phpunit = $processBuilder->getProcess();
        $phpunit->run(
            function ($type, $buffer) {
                $this->output->write($buffer);
            }
        );

        return $phpunit->isSuccessful();
    }

    /**
     * @return bool
     */
    protected function codeStyle()
    {
        $succeed = true;
        foreach (GitUtils::commitedFiles() as $file) {
            $fixers = '
                -psr0,eof_ending,indentation,linefeed,lowercase_keywords,trailing_spaces,
                short_tag,php_closing_tag,extra_empty_lines,elseif,function_declaration
            ';
            $processBuilder = new ProcessBuilder(array(
                'php',
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

        return $succeed;
    }

    /**
     * @return bool
     */
    private function codeStylePsr()
    {
        $succeed = true;
        foreach (GitUtils::commitedFiles() as $file) {
            $processBuilder = new ProcessBuilder(array('php', 'bin/phpcs', '--standard=PSR2', $file));
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
        $docPath = __DIR__.'/pre-commit';
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
        if (!@readlink($symlinkName)) {
            $processBuilder = new ProcessBuilder(array('rm', '-rf', $symlinkTarget));
            $process = $processBuilder->getProcess();
            $process->run();

            if (symlink($symlinkName, $symlinkTarget) === false) {
                throw new \Exception('Error occured when trying to create a symlink.');
            }
        }
    }
}
