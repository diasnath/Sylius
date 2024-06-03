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

namespace Lyranetwork\Lyra\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PasswordType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'always_empty' => true,
            'trim' => false,
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'password';
    }
}
