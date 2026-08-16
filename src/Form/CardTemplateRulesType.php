<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CardTemplate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CardTemplateRulesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('requirement', TextType::class, [
                'required' => false,
            ])
            ->add('isDefault', CheckboxType::class, [
                'required' => false,
                'false_values' => [null, '0', ''],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CardTemplate::class,
            'csrf_protection' => false,
        ]);
    }
}
