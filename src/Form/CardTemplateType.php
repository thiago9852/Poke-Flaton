<?php

namespace App\Form;

use App\Entity\CardTemplate;
use App\Enum\Medal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CardTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $medalChoices = [];
        foreach (Medal::cases() as $medal) {
            $label = $medal->getTitle() . ' (' . $medal->value . ')';
            $medalChoices[$label] = $medal->value;
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nome do Plano de Fundo',
                'required' => true,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Imagem de Fundo',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'maxSizeMessage' => 'O arquivo é muito grande (máximo {{ limit }}).',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'application/pdf'],
                    ]),
                    
                ],
            ])
            ->add('requirement', TextType::class, [
                'label' => 'Descrição do Requisito',
                'required' => true,
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Disponível por padrão (sem requisitos)?',
                'required' => false,
            ])
            ->add('reqMedal', ChoiceType::class, [
                'label' => 'Medalha Necessária',
                'required' => false,
                'placeholder' => 'Nenhuma medalha',
                'choices' => $medalChoices,
            ])
            ->add('reqTier', ChoiceType::class, [
                'label' => 'Nível da Medalha',
                'required' => false,
                'placeholder' => 'Nenhum nível',
                'choices' => [
                    'Bronze' => 'bronze',
                    'Prata' => 'silver',
                    'Ouro' => 'gold',
                ],
            ])
            ->add('reqGoldCount', IntegerType::class, [
                'label' => 'Quantidade de Medalhas de Ouro necessárias',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CardTemplate::class,
        ]);
    }
}
