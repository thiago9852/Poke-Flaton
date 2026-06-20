<?php

namespace App\Form;

use App\Entity\EvolutionRule;
use App\Enum\EvolutionStone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class EvolutionRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $stoneChoices = [];
        foreach (EvolutionStone::cases() as $stone) {
            $stoneChoices[$stone->getLabel()] = $stone->value;
        }
        $stoneChoices['Outro / Especial'] = 'custom';

        $builder
            ->add('basePokemon', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'O Pokémon base é obrigatório.']),
                ],
            ])
            ->add('evolvedPokemon', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'O Pokémon evoluído é obrigatório.']),
                ],
            ])
            ->add('evolutionStone', ChoiceType::class, [
                'mapped' => false,
                'choices' => $stoneChoices,
                'required' => true,
            ])
            ->add('customStone', TextType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('gender', ChoiceType::class, [
                'choices' => [
                    'Ambos' => 'both',
                    'Macho' => 'male',
                    'Fêmea' => 'female',
                ],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EvolutionRule::class,
            'csrf_protection' => false,
        ]);
    }
}
