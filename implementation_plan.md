# Plano de Implementação: Recompensas por Ranking e Sincronização de Templates

Este plano detalha as alterações necessárias para suportar recompensas baseadas na posição do usuário nos rankings (Curtidas e Medalhas) e refatorar os planos de fundo (templates) para serem sincronizados via API do GitHub, eliminando o upload de arquivos locais.

## User Review Required

> [!IMPORTANT]
> **Migração de Banco de Dados:** Será necessário rodar novas migrations para adicionar as colunas `req_rank_type` e `req_rank_pos` nas tabelas `avatar`, `card_template` e `title`.
>
> **Reset de Planos de Fundo:** A tabela `card_template` será limpa e repopulada automaticamente com os modelos do GitHub.

---

## Proposed Changes

### 1. Banco de Dados e Entidades

#### [MODIFY] [Avatar.php](file:///c:/Users/thiag/OneDrive/Documentos/GitHub/pokeMoveset/src/Entity/Avatar.php)
Adicionar os campos:
- `reqRankType` (string, nullable) - `'likes'` ou `'medals'`
- `reqRankPos` (int, nullable) - `1`, `2` ou `3`

#### [MODIFY] [CardTemplate.php](file:///c:/Users/thiag/OneDrive/Documentos/GitHub/pokeMoveset/src/Entity/CardTemplate.php)
Adicionar os mesmos campos `reqRankType` e `reqRankPos`.

#### [MODIFY] [Title.php](file:///c:/Users/thiag/OneDrive/Documentos/GitHub/pokeMoveset/src/Entity/Title.php)
Adicionar os mesmos campos `reqRankType` e `reqRankPos`.

#### [NEW] [VersionXXXXXXXXXXXXXX.php](file:///c:/Users/thiag/OneDrive/Documentos/GitHub/pokeMoveset/migrations)
Criar uma nova migration para adicionar as colunas `req_rank_type` e `req_rank_pos` nas três tabelas.

---

### 2. Lógica de Negócios e Serviços

#### [MODIFY] [TrainerProfileService.php](file:///c:/Users/thiag/OneDrive/Documentos/GitHub/pokeMoveset/src/Service/TrainerProfileService.php)
1. **Mapeamento de URLs dos Templates:** Alterar de `/uploads/templates/...` para a URL do GitHub raw (`https://raw.githubusercontent.com/thiago9852/pokemon-sprite/main/sprites/src/templates/...`).
2. **Método `getUserRankingPositions(User $user)`:** Nova função que calcula a colocação do usuário nos rankings de Curtidas e Medalhas.
3. **Validação de Bloqueio por Ranking:**
   - Em `getAvatarUnlockStatus()`, `getTitlesUnlockStatus()` e `getTemplatesUnlockStatus()`, validar se a posição do usuário no ranking atende aos requisitos (`reqRankType` e `reqRankPos`).
4. **Sincronização de Templates:**
   - Implementar `syncTemplatesFromApi()` e `resetAndSyncTemplates()` consumindo `https://api.github.com/repos/thiago9852/pokemon-sprite/contents/sprites/src/templates`.
5. **Associação de Medalhas com Ranking:**
   - Em `getMedalStatus()`, para a medalha `Acclaimed` (Curtidas) e `Popular` (Medalhas/Seguidores), aplicar o tier correspondente à posição do ranking (Top 1 = Gold, Top 2 = Silver, Top 3 = Bronze).

---

### 3. Controladores (Admin e TrainerCard)

#### [MODIFY] [AdminController.php](file:///c:/Users/thiag/OneDrive/Documentos/GitHub/pokeMoveset/src/Controller/AdminController.php)
1. Remover lógica de upload de imagem para templates.
2. Adicionar as rotas `/admin/templates/sync` e `/admin/templates/reset`.
3. Atualizar a rota `/admin/avatar/{id}/update` para salvar as novas regras de ranking (`reqRankType`, `reqRankPos`).
4. Criar rotas `/admin/template/{id}/update` e `/admin/title/{id}/update` para salvar as novas regras de ranking e medalhas dos templates e títulos de forma similar aos avatares.

---

### 4. Interface Administrativa (Views)

#### [MODIFY] [templates/admin/index.html.twig](file:///c:/Users/thiag/OneDrive/Documentos/GitHub/pokeMoveset/templates/admin/index.html.twig)
1. Adicionar os seletores de tipo de ranking e posição nos formulários de edição de avatares, títulos e templates.
2. Mudar a seção de Planos de Fundo para usar a tabela de edição em lote (com Sincronizar e Restaurar) ao invés do formulário de upload.

---

## Verification Plan

### Automated Steps
O usuário deverá rodar em seu terminal:
1. `php bin/console doctrine:migrations:diff` (para gerar a estrutura das novas colunas).
2. `php bin/console doctrine:migrations:migrate` (para criar as colunas no banco).

### Manual Verification
- Testar a sincronização dos templates pelo Admin.
- Associar um avatar/template/título para o "Top 1 de Curtidas" e validar que apenas o usuário em 1º lugar consegue selecioná-lo.
