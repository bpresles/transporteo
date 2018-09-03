<?php

declare(strict_types=1);

namespace Akeneo\PimMigration\Infrastructure\MigrationStep;

use Akeneo\PimMigration\Domain\MigrationStep\s015_SourcePimApiConfiguration\SourcePimApiConfigurationException;
use Akeneo\PimMigration\Domain\Pim\PimApiClientBuilder;
use Akeneo\PimMigration\Domain\Pim\PimApiParameters;
use Akeneo\PimMigration\Infrastructure\TransporteoStateMachine;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Workflow\Event\Event;

/**
 * Step to configure the API client of the source PIM.
 *
 * @author    Laurent Petard <laurent.petard@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 */
class S015FromSourcePimConfiguredToSourcePimApiConfigured extends AbstractStateMachineSubscriber implements StateMachineSubscriber
{
    /** @var PimApiClientBuilder */
    private $apiClientBuilder;

    public function __construct(Translator $translator, LoggerInterface $logger, PimApiClientBuilder $apiClientBuilder)
    {
        parent::__construct($translator, $logger);

        $this->apiClientBuilder = $apiClientBuilder;
    }

    public static function getSubscribedEvents()
    {
        return [
            'workflow.transporteo.transition.source_pim_api_configuration' => 'onSourcePimApiConfiguration',
        ];
    }

    public function onSourcePimApiConfiguration(Event $event)
    {
        /** @var TransporteoStateMachine $stateMachine */
        $stateMachine = $event->getSubject();

        $sourceApiParameters = new PimApiParameters(
            $this->askForBaseUri($stateMachine->getDefaultResponse('api_base_uri_source_pim')),
            $this->askForClientId($stateMachine->getDefaultResponse('api_client_id')),
            $this->askForSecret($stateMachine->getDefaultResponse('api_secret')),
            $this->askForUserName($stateMachine->getDefaultResponse('api_user_name')),
            $this->askForUserPwd($stateMachine->getDefaultResponse('api_user_pwd'))
        );

        try {
            $apiClient = $this->apiClientBuilder->build($sourceApiParameters);

            // Only to check the API authentication before starting the migration.
            $apiClient->getProductApi()->all(1);
        } catch (\Exception $exception) {
            throw new SourcePimApiConfigurationException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $stateMachine->setSourcePimApiParameters($sourceApiParameters);
    }

    private function askForBaseUri(string $defaultResponse): string
    {
        $question = $this->translator->trans(
            'from_source_pim_configured_to_source_pim_api_configured.on_source_pim_api_configuration.base_uri.question'
        );

        $validator = function ($answer) {
            // This URI validation regex is intentionally imperfect.
            // It's goal is only to avoid common mistakes like forgetting "http", or adding parameters from a copy/paste.
            if (0 === preg_match('~^https?:\/\/[a-z0-9]+[a-z0-9\-\.]*[a-z0-9]+\/?(?:\:\d{1,4})?$~i', $answer)) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'from_source_pim_configured_to_source_pim_api_configured.on_source_pim_api_configuration.base_uri.error_message'
                    )
                );
            }
        };

        return $this->printerAndAsker->askSimpleQuestion($question, $defaultResponse, $validator);
    }

    private function askForClientId(string $defaultResponse): string
    {
        $question = $this->translator->trans(
            'from_source_pim_configured_to_source_pim_api_configured.on_source_pim_api_configuration.client_id_question'
        );

        return $this->printerAndAsker->askSimpleQuestion($question, $defaultResponse);
    }

    private function askForSecret(string $defaultResponse): string
    {
        $question = $this->translator->trans(
            'from_source_pim_configured_to_source_pim_api_configured.on_source_pim_api_configuration.secret_question'
        );

        return $this->printerAndAsker->askSimpleQuestion($question, $defaultResponse);
    }

    private function askForUserName(string $defaultResponse): string
    {
        $question = $this->translator->trans(
            'from_source_pim_configured_to_source_pim_api_configured.on_source_pim_api_configuration.user_name_question'
        );

        return $this->printerAndAsker->askSimpleQuestion($question, $defaultResponse);
    }

    private function askForUserPwd(string $defaultResponse): string
    {
        $question = $this->translator->trans(
            'from_source_pim_configured_to_source_pim_api_configured.on_source_pim_api_configuration.user_pwd_question'
        );

        return $this->printerAndAsker->askSimpleQuestion($question, $defaultResponse);
    }
}
