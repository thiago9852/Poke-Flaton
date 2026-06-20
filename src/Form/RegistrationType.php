<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\RegionEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $regionChoices = [];
        foreach (RegionEnum::cases() as $region) {
            $genRoman = match ($region) {
                RegionEnum::Kanto => 'I',
                RegionEnum::Johto => 'II',
                RegionEnum::Hoenn => 'III',
                RegionEnum::Sinnoh => 'IV',
                RegionEnum::Unova => 'V',
                RegionEnum::Kalos => 'VI',
                RegionEnum::Alola => 'VII',
                RegionEnum::Galar => 'VIII',
                RegionEnum::Paldea => 'IX',
            };
            $regionChoices[$region->value . ' — Gen ' . $genRoman] = $region->value;
        }

        $builder
            ->add('username', TextType::class, [
                'label' => 'Nome de Usuário',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'O nome de usuário não pode estar vazio.']),
                    new Length([
                        'min' => 3,
                        'max' => 30,
                        'minMessage' => 'O nome de usuário deve ter entre 3 e 30 caracteres.',
                        'maxMessage' => 'O nome de usuário deve ter entre 3 e 30 caracteres.',
                    ]),
                ],
            ])
            ->add('apelido', TextType::class, [
                'label' => 'Apelido',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 30,
                        'maxMessage' => 'O apelido deve ter no máximo 30 caracteres.',
                    ]),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'As senhas informadas não coincidem.',
                'required' => true,
                'first_options'  => ['label' => 'Senha'],
                'second_options' => ['label' => 'Confirmar Senha'],
                'constraints' => [
                    new NotBlank(['message' => 'A senha não pode estar vazia.']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'A senha deve ter no mínimo 6 caracteres.',
                    ]),
                ],
            ])
            ->add('regional', ChoiceType::class, [
                'label' => 'Sua Região',
                'choices' => $regionChoices,
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
        ]);
    }
}
