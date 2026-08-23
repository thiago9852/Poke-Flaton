<div align="center">

# Mov Set

**Portal estratégico para treinadores Pokémon: monte movesets, times, tier lists e acompanhe sua evolução como treinador.**

[![CI](https://github.com/thiago9852/Poke-Flaton/actions/workflows/ci.yml/badge.svg)](https://github.com/thiago9852/Poke-Flaton/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.2-000000?logo=symfony&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)

</div>

Projeto pessoal construído com Symfony consumindo dados em tempo real da [PokeAPI](https://pokeapi.co/) — sem duplicar o dex inteiro no banco, o app cacheia e enriquece os dados sob demanda, guardando localmente apenas o que é específico da comunidade (movesets, times, tier lists, perfis de treinador).

## Índice

- [Screenshots](#screenshots)
- [Funcionalidades](#funcionalidades)
- [Arquitetura](#arquitetura)
- [Stack](#stack)
- [Como rodar](#como-rodar)
- [Qualidade de código](#qualidade-de-código)
- [Estrutura de pastas](#estrutura-de-pastas)
- [Roadmap](#roadmap--débito-técnico-conhecido)

## Screenshots

<!--
  Sugestão de capturas (salvar em docs/screenshots/ com esses nomes):
  - home.png            → página inicial ("/")
  - pokedex.png         → listagem de Pokémon com filtros ("/pokemons")
  - pokemon-detail.png  → detalhe de um Pokémon com movesets ("/pokemon/{name}")
  - team-builder.png    → montagem de time ("/team")
  - tier-list.png       → tier list em edição ("/tier-list/{id}")
  - trainer-card.png    → card de treinador ("/trainer-card")
-->
<div align="center">
  <img src="docs/screenshots/home.png" alt="Página inicial do Mov Set" width="49%" />
  <img src="docs/screenshots/pokedex.png" alt="Pokédex com filtros por tipo e geração" width="49%" />
  <img src="docs/screenshots/team-builder.png" alt="Montador de times" width="49%" />
  <img src="docs/screenshots/trainer-card.png" alt="Card de treinador" width="49%" />
</div>

## Funcionalidades

**Movesets** — criação e votação de conjuntos de golpes por Pokémon (padrão, PVP, dungeons), com moderação de sugestões pelo admin.

**Team Builder** — montagem de times com busca de Pokémon integrada à PokeAPI.

**Tier List** — criação de tier lists customizadas por categoria (progressão PVE, PVP, dungeons/bosses, clã/time), públicas para a comunidade.

**Trainer Card** — perfil público do treinador com:
- Avatar e plano de fundo (template) customizáveis
- Mochila de TMs desbloqueadas
- Sistema de seguidores

**Login social** — autenticação via Google Sign-In, além de registro tradicional.

**Painel administrativo** — CRUD de templates de card, avatares, variações de Pokémon e regras de evolução; moderação de movesets e localizações sugeridas pela comunidade; importação em lote via JSON.

**Integração com Discord** — API própria (`/api/discord/*`) para bots consultarem dados de Pokémon e traduzir natures PT-BR ↔ EN.

## Arquitetura

O dado de Pokémon (stats, tipos, sprites, evoluções) **não vive no banco local** — é buscado da PokeAPI sob demanda e cacheado (`Symfony Cache`, TTL de dias). O banco local guarda apenas o que é específico da aplicação: usuários, movesets, tier lists, perfis de treinador.

```mermaid
flowchart LR
    subgraph Cliente
        Browser["Navegador\n(Stimulus + Turbo)"]
    end

    subgraph Symfony["Aplicação Symfony"]
        Controller["Controllers"]
        Service["Services\n(PokeApiPokemonFetcher,\nTrainerProfileService...)"]
        Cache[("Symfony Cache")]
    end

    DB[("MySQL\nusuários · movesets ·\ntier lists · perfis")]
    PokeAPI["PokeAPI\n(dados de Pokémon)"]

    Browser --> Controller
    Controller --> Service
    Service --> Cache
    Cache -. miss .-> PokeAPI
    Service --> DB
    Controller --> Browser
```

Documentação técnica mais detalhada (módulos, modelo de dados, decisões e débito técnico conhecido) em **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.2+, Symfony 7.2 (Doctrine ORM, Security, Forms, Serializer) |
| Frontend | Stimulus + Turbo (Symfony UX), AssetMapper (sem bundler externo) |
| Banco de dados | MySQL 8 |
| Dados externos | [PokeAPI](https://pokeapi.co/) via `HttpClient` + cache |
| Infra | Docker (Nginx + PHP-FPM em dev, Apache standalone em produção) |
| Qualidade | PHPStan, PHP-CS-Fixer, Rector, PHPUnit, GitHub Actions |

## Como rodar

### Com Docker (recomendado)

Pré-requisitos: Docker e Docker Compose.

```bash
cp .env .env.local
docker compose up -d --build
```

O `entrypoint` do container `php` instala as dependências do Composer e roda as migrations automaticamente no primeiro `up`.

| Serviço | URL |
|---|---|
| Aplicação (Nginx) | http://localhost:8080 |
| MySQL | localhost:3306 |
| Mailpit (emails em dev) | http://localhost:8025 |

### Sem Docker

```bash
composer install
php bin/console doctrine:migrations:migrate
symfony server:start
```

Ajuste `DATABASE_URL` no `.env.local` para seu MySQL local.

## Qualidade de código

```bash
composer phpstan     # análise estática (nível 5, com baseline para débito legado)
composer cs-fix       # aplica o padrão de código (PHP-CS-Fixer)
composer cs-check     # verifica sem aplicar
composer rector       # refactors automáticos (Rector)
composer test         # PHPUnit
```

Todas essas verificações rodam no CI (`.github/workflows/ci.yml`) a cada push/PR.

## Estrutura de pastas

```
src/
├── Controller/
│   ├── Admin/          # painel administrativo, dividido por domínio
│   └── ...              # rotas públicas (Pokémon, Team, TierList, TrainerCard...)
├── Entity/               # entidades Doctrine
├── Form/                 # formulários (admin e cadastro)
├── Repository/
├── Service/
│   └── PokeApi/          # camada de integração com a PokeAPI (fetch + cache + validação)
└── Enum/
templates/                # Twig, organizado por domínio
docs/                      # documentação técnica e screenshots
```
