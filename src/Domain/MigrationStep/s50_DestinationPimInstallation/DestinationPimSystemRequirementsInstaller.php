<?php

declare(strict_types=1);

namespace Akeneo\PimMigration\Domain\MigrationStep\s50_DestinationPimInstallation;
use Akeneo\PimMigration\Domain\Pim\DestinationPim;

/**
 * Install all system requirements (e.g: mysql / elasticsearch / folders).
 *
 * @author    Anael Chardan <anael.chardan@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 */
interface DestinationPimSystemRequirementsInstaller
{
    public function install(DestinationPim $destinationPim): void;
}
