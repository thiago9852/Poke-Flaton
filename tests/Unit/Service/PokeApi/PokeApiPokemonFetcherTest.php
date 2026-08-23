<?php

namespace App\Tests\Unit\Service\PokeApi;

use App\Service\PokeApi\PokeApiPokemonFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PokeApiPokemonFetcherTest extends TestCase
{
    #[DataProvider('aliasProvider')]
    public function testResolveNameAlias(string $input, string $expected): void
    {
        $this->assertSame($expected, PokeApiPokemonFetcher::resolveNameAlias($input));
    }

    public static function aliasProvider(): iterable
    {
        yield 'form with default variety' => ['burmy', 'burmy-plant'];
        yield 'form is case-insensitive' => ['BURMY', 'burmy-plant'];
        yield 'form trims whitespace' => [' wormadam ', 'wormadam-plant'];
        yield 'pokemon without alias returns itself' => ['pikachu', 'pikachu'];
    }

    public function testGetPokeApiNameMapsCanonicalToApiName(): void
    {
        $this->assertSame('burmy', PokeApiPokemonFetcher::getPokeApiName('burmy-plant'));
        $this->assertSame('pikachu', PokeApiPokemonFetcher::getPokeApiName('pikachu'));
    }

    #[DataProvider('speciesNameProvider')]
    public function testGetSpeciesNameStripsFormSuffix(string $input, string $expected): void
    {
        $this->assertSame($expected, PokeApiPokemonFetcher::getSpeciesName($input));
    }

    public static function speciesNameProvider(): iterable
    {
        yield 'mega suffix' => ['charizard-mega-x', 'charizard'];
        yield 'regional suffix' => ['ninetales-alola', 'ninetales'];
        yield 'striped form via explicit map' => ['burmy-plant', 'burmy'];
        yield 'no suffix returns itself' => ['pikachu', 'pikachu'];
    }
}
