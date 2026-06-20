<?php

namespace App\Form;

use App\Entity\PokemonVariation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThan;

class PokemonVariationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', IntegerType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'O ID da variação é obrigatório.']),
                    new GreaterThan(['value' => 0, 'message' => 'O ID deve ser maior que zero.']),
                ],
            ])
            ->add('baseId', IntegerType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'O ID base é obrigatório.']),
                    new GreaterThan(['value' => 0, 'message' => 'O ID base deve ser maior que zero.']),
                ],
            ])
            ->add('name', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'O nome da variação é obrigatório.']),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PokemonVariation::class,
            'csrf_protection' => false,
        ]);
    }
}
