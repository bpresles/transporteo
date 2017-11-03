<?php

declare(strict_types=1);

namespace Akeneo\PimMigration\Domain\MigrationStep\s150_ProductVariationMigration\VariantGroup;

use Akeneo\PimMigration\Domain\DataMigration\DataMigrator;
use Akeneo\PimMigration\Domain\DataMigration\TableMigrator;
use Akeneo\PimMigration\Domain\MigrationStep\s150_ProductVariationMigration\FamilyRepository;
use Akeneo\PimMigration\Domain\MigrationStep\s150_ProductVariationMigration\InvalidVariantGroupException;
use Akeneo\PimMigration\Domain\Pim\DestinationPim;
use Akeneo\PimMigration\Domain\Pim\SourcePim;

/**
 * Migrates variant groups data according to the new product variation model.
 *
 * @author    Laurent Petard <laurent.petard@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 */
class VariantGroupMigrator implements DataMigrator
{
    /** @var VariantGroupRepository */
    private $variantGroupRepository;

    /** @var TableMigrator */
    private $tableMigrator;

    /** @var VariantGroupValidator */
    private $variantGroupValidator;

    /** @var MigrationCleaner */
    private $variantGroupMigrationCleaner;

    /** @var FamilyCreator */
    private $familyCreator;

    /** @var ProductMigrator */
    private $productMigrator;

    /** @var FamilyRepository */
    private $familyRepository;

    public function __construct(
        VariantGroupRepository $variantGroupRepository,
        VariantGroupValidator $variantGroupValidator,
        FamilyCreator $familyCreator,
        FamilyRepository $familyRepository,
        ProductMigrator $productMigrator,
        MigrationCleaner $variantGroupMigrationCleaner,
        TableMigrator $tableMigrator
    ) {
        $this->variantGroupRepository = $variantGroupRepository;
        $this->tableMigrator = $tableMigrator;
        $this->variantGroupValidator = $variantGroupValidator;
        $this->variantGroupMigrationCleaner = $variantGroupMigrationCleaner;
        $this->familyCreator = $familyCreator;
        $this->productMigrator = $productMigrator;
        $this->familyRepository = $familyRepository;
    }

    public function migrate(SourcePim $sourcePim, DestinationPim $destinationPim): void
    {
        $this->tableMigrator->migrate($sourcePim, $destinationPim, 'pim_catalog_group_attribute');
        $this->tableMigrator->migrate($sourcePim, $destinationPim, 'pim_catalog_product_template');

        $this->removeInvalidVariantGroups($destinationPim);

        $variantGroupCombinations = $this->retrieveVariantGroupCombinationsToMigrate($destinationPim);

        foreach ($variantGroupCombinations as $variantGroupCombination) {
            $this->migrateVariantGroupCombination($variantGroupCombination, $destinationPim);
        }

        $this->variantGroupMigrationCleaner->clean($destinationPim);

        $numberOfRemovedInvalidVariantGroups = $this->variantGroupRepository->retrieveNumberOfRemovedInvalidVariantGroups($destinationPim);
        if ($numberOfRemovedInvalidVariantGroups > 0) {
            throw new InvalidVariantGroupException($numberOfRemovedInvalidVariantGroups);
        }
    }

    /**
     * Remove softly the invalid variant-groups from the migration by changing their type to a specific one.
     */
    private function removeInvalidVariantGroups(DestinationPim $pim): void
    {
        $variantGroups = $this->variantGroupRepository->retrieveVariantGroups($pim);

        foreach ($variantGroups as $variantGroup) {
            if (!$this->variantGroupValidator->isVariantGroupValid($variantGroup, $pim)) {
                $this->variantGroupRepository->softlyRemoveVariantGroup($variantGroup->getCode(), $pim);
            }
        }
    }

    private function retrieveVariantGroupCombinationsToMigrate(DestinationPim $pim): \Traversable
    {
        $variantGroupCombinations = $this->retrieveVariantGroupCombinations($pim);

        foreach ($variantGroupCombinations as $variantGroupCombination) {
            if ($this->variantGroupValidator->isVariantGroupCombinationValid($variantGroupCombination, $pim)) {
                yield $variantGroupCombination;
            } else {
                $this->removeVariantGroupCombination($variantGroupCombination, $pim);
            }
        }
    }

    /**
     * Retrieves and build the variant groups combinations.
     */
    private function retrieveVariantGroupCombinations(DestinationPim $pim)
    {
        $variantGroupCombinations = $this->variantGroupRepository->retrieveVariantGroupCombinations($pim);
        $familyIncrement = 1;
        $family = null;

        foreach ($variantGroupCombinations as $variantGroupCombination) {
            if (null !== $family && $variantGroupCombination['family_code'] === $family->getCode()) {
                ++$familyIncrement;
            } else {
                $familyIncrement = 1;
                $family = $this->familyRepository->findByCode($variantGroupCombination['family_code'], $pim);
            }

            $groups = explode(',', $variantGroupCombination['groups']);
            $attributes = $this->variantGroupRepository->retrieveGroupAttributes($groups[0], $pim);

            yield new VariantGroupCombination(
                $family,
                $variantGroupCombination['family_code'].'_'.$familyIncrement,
                explode(',', $variantGroupCombination['axes']),
                $groups,
                $attributes
            );
        }
    }

    private function removeVariantGroupCombination(VariantGroupCombination $variantGroupCombination, DestinationPim $pim): void
    {
        foreach ($variantGroupCombination->getGroups() as $groupCode) {
            $this->variantGroupRepository->softlyRemoveVariantGroup($groupCode, $pim);
        }
    }

    private function migrateVariantGroupCombination(VariantGroupCombination $variantGroupCombination, DestinationPim $pim)
    {
        $familyVariant = $this->familyCreator->createFamilyVariant($variantGroupCombination, $pim);

        $this->productMigrator->migrateProductModels($variantGroupCombination, $pim);
        $this->productMigrator->migrateProductVariants($familyVariant, $variantGroupCombination, $pim);
    }
}
