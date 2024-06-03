<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Lyra;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class LyranetworkLyraPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
