<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\FeatureFlag;

use PrestaShop\PrestaShop\Core\FeatureFlag\Handler\DbHandler;
use PrestaShop\PrestaShop\Core\FeatureFlag\Handler\DotEnvHandler;
use PrestaShop\PrestaShop\Core\FeatureFlag\Handler\EnvHandler;
use PrestaShopBundle\Entity\Repository\FeatureFlagRepository;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class FeatureFlagService implements ServiceSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $locator,
        private readonly FeatureFlagRepository $featureFlagRepository
    ) {
    }

    /**
     * Subscribe all handlers in this service container.
     *
     * @return string[]
     */
    public static function getSubscribedServices(): array
    {
        return [
            FeatureFlagSettings::TYPE_ENV => EnvHandler::class,
            FeatureFlagSettings::TYPE_DOTENV => DotEnvHandler::class,
            FeatureFlagSettings::TYPE_DB => DbHandler::class,
        ];
    }

    /**
     * Get used handle for the feature flag.
     */
    private function getHandler(string $featureFlagName): TypeHandlerInterface
    {
        $featureFlag = $this->featureFlagRepository->getByName($featureFlagName);
        if (null !== $featureFlag) {
            foreach ($featureFlag->getTypeOrder() as $type) {
                if ($this->locator->has($type)) {
                    $handler = $this->locator->get($type);
                    if ($handler->canBeUsed($featureFlagName)) {
                        return $handler;
                    }
                }
            }
            throw new \RuntimeException(sprintf('No handler can be used for feature flag %s.', $featureFlagName));
        }
        throw new \RuntimeException(sprintf('The feature flag %s doesn\'t exist.', $featureFlagName));
    }

    /**
     * Get type of handler used by this feature flag.
     */
    public function getTypeUsed(string $featureFlagName): string
    {
        return $this->getHandler($featureFlagName)->getTypeName();
    }

    /**
     * Is the handler used by this feature flag read only?
     */
    public function isReadonly(string $featureFlagName): bool
    {
        return $this->getHandler($featureFlagName)->isReadonly();
    }

    /**
     * Is this feature flag enable?
     */
    public function isEnabled(string $featureFlagName): bool
    {
        return $this->getHandler($featureFlagName)->isEnabled($featureFlagName);
    }

    /**
     * Is this feature flag disable?
     */
    public function isDisabled(string $featureFlagName): bool
    {
        return !$this->isEnabled($featureFlagName);
    }

    /**
     * Enable the feature flag by his handler.
     */
    public function enable(string $featureFlagName): void
    {
        $this->getHandler($featureFlagName)->enable($featureFlagName);
    }

    /**
     * Disable the feature flag by his handler.
     */
    public function disable(string $featureFlagName): void
    {
        $this->getHandler($featureFlagName)->disable($featureFlagName);
    }
}
