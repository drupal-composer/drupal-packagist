<?php

/*
 * This file is part of Packagist.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *     Nils Adermann <naderman@naderman.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Packagist\WebBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class AbandonedType
 *
 * Form used to acquire replacement Package information for abandoned package.
 *
 * @package Packagist\WebBundle\Form\Type
 */
class AbandonedType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'replacement',
            'text',
            array(
                'required' => false,
                'label'    => 'Replacement package',
                'attr'     => array('placeholder' => 'optional')
            )
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'package';
    }
}
