<?php

declare(strict_types=1);

namespace spec\Akeneo\PimMigration\Domain\MigrationStep\s130_ReferenceDataMigration;

use Akeneo\PimMigration\Domain\FileSystemHelper;
use Akeneo\PimMigration\Domain\MigrationStep\s130_ReferenceDataMigration\MigrationBundleInstaller;
use Akeneo\PimMigration\Domain\Pim\Pim;
use PhpSpec\ObjectBehavior;
use Psr\Log\LoggerInterface;
use resources\Akeneo\PimMigration\ResourcesFileLocator;

/**
 * Spec for Migration Bundle Installer.
 *
 * @author    Anael Chardan <anael.chardan@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 */
class MigrationBundleInstallerSpec extends ObjectBehavior
{
    public function let(FileSystemHelper $fileSystem, LoggerInterface $logger)
    {
        $this->beConstructedWith($fileSystem, $logger);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(MigrationBundleInstaller::class);
    }

    public function it_installs_a_community_edition(
        Pim $pim,
        $fileSystem
    ) {
        $destinationPimPath = '/a-path';
        $pim->absolutePath()->willReturn($destinationPimPath);

        $referenceDataMigrationConfigDir = sprintf(
            '%s%sDomain%sMigrationStep%ss130_ReferenceDataMigration%sconfig',
            ResourcesFileLocator::getSrcDir(),
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR
        );

        $archivePath = sprintf(
            '%s%sAkeneo',
            $referenceDataMigrationConfigDir,
            DIRECTORY_SEPARATOR
        );
        $fileSystem->copyDirectory($archivePath, '/a-path/src/Akeneo')->shouldBeCalled();


        $appKernelPath = sprintf(
            '%s%sapp%sAppKernel.php',
            $destinationPimPath,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR
        );

        $pim->isEnterpriseEdition()->willReturn(false);

        $fileSystem->getFileLine($appKernelPath, 23)->willReturn(
            '            // your app bundles should be registered here'.PHP_EOL
        );

        $lineToAdd = "            new Akeneo\Bundle\MigrationBundle\AkeneoMigrationBundle(),".PHP_EOL;

        $fileSystem->updateLineInFile($appKernelPath, 23, $lineToAdd)->shouldBeCalled();

        $this->install($pim);
    }

    public function it_throws_an_exception_for_community_edition(
        Pim $pim,
        $fileSystem
    ) {
        $destinationPimPath = '/a-path';
        $pim->absolutePath()->willReturn($destinationPimPath);

        $referenceDataMigrationConfigDir = sprintf(
            '%s%sDomain%sMigrationStep%ss130_ReferenceDataMigration%sconfig',
            ResourcesFileLocator::getSrcDir(),
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR
        );

        $archivePath = sprintf(
            '%s%sAkeneo',
            $referenceDataMigrationConfigDir,
            DIRECTORY_SEPARATOR
        );
        $fileSystem->copyDirectory($archivePath, '/a-path/src/Akeneo')->shouldBeCalled();


        $appKernelPath = sprintf(
            '%s%sapp%sAppKernel.php',
            $destinationPimPath,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR
        );

        $pim->isEnterpriseEdition()->willReturn(false);

        $fileSystem->getFileLine($appKernelPath, 23)->willReturn(
            '          A weird line'.PHP_EOL
        );

        $this->shouldThrow(new \InvalidArgumentException('The AppKernel is not a raw kernel'))->during('install', [$pim]);
    }
}
