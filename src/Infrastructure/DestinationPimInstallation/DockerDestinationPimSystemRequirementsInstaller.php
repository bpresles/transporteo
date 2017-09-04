<?php

declare(strict_types=1);

namespace Akeneo\PimMigration\Infrastructure\DestinationPimInstallation;

use Akeneo\PimMigration\Domain\Pim\DestinationPim;
use Akeneo\PimMigration\Domain\MigrationStep\s50_DestinationPimInstallation\DestinationPimSystemRequirementsNotBootable;
use Akeneo\PimMigration\Domain\MigrationStep\s50_DestinationPimInstallation\DestinationPimSystemRequirementsInstaller;
use Akeneo\PimMigration\Infrastructure\Command\CommandLauncher;
use Ds\Set;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Install Pim System Requirements through docker.
 *
 * @author    Anael Chardan <anael.chardan@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 */
class DockerDestinationPimSystemRequirementsInstaller implements DestinationPimSystemRequirementsInstaller
{
    /** @var CommandLauncher */
    private $destinationPimCommandLauncher;

    public function __construct(CommandLauncher $destinationPimCommandLauncher)
    {
        $this->destinationPimCommandLauncher = $destinationPimCommandLauncher;
    }

    public function install(DestinationPim $destinationPim): void
    {
        $dockerComposeDistFilePath = sprintf(
            '%s%sdocker-compose.yml.dist',
            $destinationPim->absolutePath(),
            DIRECTORY_SEPARATOR
        );

        $dockerComposeDestinationPath = sprintf(
            '%s%sdocker-compose.yml',
            $destinationPim->absolutePath(),
            DIRECTORY_SEPARATOR
        );

        $fs = new Filesystem();

        $fs->copy($dockerComposeDistFilePath, $dockerComposeDestinationPath);

        $launchDockerComposeDaemon = new Process('docker-compose up -d', $destinationPim->absolutePath());

        $launchDockerComposeDaemon->run();

        if (!$this->dockerComposeInfrastructureIsUp($destinationPim->absolutePath())) {
            throw new DestinationPimSystemRequirementsNotBootable(
                'Docker cannot boot the install system, please check `docker-compose ps` in '.$destinationPim->absolutePath()
            );
        }

        $this->destinationPimCommandLauncher->runCommand(
            new ComposerUpdateCommand(), $destinationPim->absolutePath(), true
        );
        $this->destinationPimCommandLauncher->runCommand(
            new PrepareRequiredDirectoriesCommand(), $destinationPim->absolutePath(), true
        );
        $this->destinationPimCommandLauncher->runCommand(
            new DoctrineDropDatabaseCommand(), $destinationPim->absolutePath(), true
        );
        $this->destinationPimCommandLauncher->runCommand(
            new DoctrineCreateDatabaseCommand(), $destinationPim->absolutePath(), true
        );
        $this->destinationPimCommandLauncher->runCommand(
            new DoctrineCreateSchemaCommand(), $destinationPim->absolutePath(), true
        );
        $this->destinationPimCommandLauncher->runCommand(
            new DoctrineUpdateSchemaCommand(), $destinationPim->absolutePath(), true
        );
    }

    protected function dockerComposeInfrastructureIsUp(string $destinationPimPath): bool
    {
        $folderName = basename($destinationPimPath);
        $containerPrefix = str_replace(['-', '_'], '', $folderName);

        $services = [
            $containerPrefix.'_httpd_1',
            $containerPrefix.'_mysql_1',
            $containerPrefix.'_elasticsearch_1',
            $containerPrefix.'_fpm_1',
        ];

        $process = new Process('docker ps --format="{{.Names}}"', $destinationPimPath);
        $process->run();
        $output = $process->getOutput();

        $output = explode(PHP_EOL, $output);

        $servicesNames = new Set($output);

        return $servicesNames->filter(function (string $service) {
            return !empty(trim($service));
        })->contains(...$services);
    }
}
