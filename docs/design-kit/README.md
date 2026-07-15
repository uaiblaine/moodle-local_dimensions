# Design kit — Central de Competências (local_dimensions)

Biblioteca de componentes do redesign administrativo (e base para o plugin companheiro
`local_modfields`). Cada arquivo `.html` é um **preview self-contained** (tokens inline, claro/escuro
via `prefers-color-scheme`) com um marcador `@dsCard` na primeira linha para o índice do Design System.

Este kit é a **fonte única do Design System** da Central, espelhado em dois lugares mantidos em sincronia:

- esta pasta `docs/design-kit/` (fonte no repo);
- o projeto **"Dimensions — Central de Competências (admin kit)"** no Claude Design (`claude.ai/design`).

## Componentes (nível componente)
| Arquivo | Grupo | O que é |
|---|---|---|
| `tokens.html` | Fundações | Tokens **alinhados ao Moodle DS** (semântico `--mds-*`, estados, foco, elevação, escalas). |
| `states.html` | Fundações | Estados interativos (default/hover/active/disabled) + foco visível (WCAG 2.2 AA). |
| `modal-shell.html` | Shell | Cabeçalho + corpo + rodapé de modal (base de todo modal `dynamic_form`). |
| `form-section.html` | Formulário | Seção com título + descrição (explicação) e linhas de campo (texto, select, colorpicker). |
| `image-dropzone.html` | Formulário | Anexo de imagem padrão Moodle (arrasta e solta) — vazio e com arquivo. |
| `hierarchy-nav.html` | Navegação | Seletor de contexto (Sistema/Categoria + contador), trilha adaptativa, abas Estrutura/Planos. |
| `master-detail.html` | Dados | Árvore de competências + painel de detalhe; chips de framework. |
| `paginated-picker.html` | Dados | Busca server-side + resultados AJAX + paginação; controle `ruleoutcome`. |
| `cohort-assign.html` | Dados | Atribuição de plano a coortes/usuários (estilo gestão de grupos do mtube) + sync. |

> Estes componentes expressam a **visão to-be** e divergem em pontos da UI shipada hoje
> (ex.: trilha adaptativa e chips ricos que ainda não existem nos mustaches). As telas
> `screens/` reconciliam isso mostrando as-is e to-be lado a lado.

## Telas as-is ↔ to-be (`screens/`)
Réplica de cada tela da Central em **dois painéis lado a lado** — **as-is** (fiel ao output
shipado, base limpa de revisão) e **to-be** (proposta) — com **badge de ID** em cada elemento.
Formato `@dsCard`, grupo "Telas (as-is ↔ to-be)" no índice do DS.

| Arquivo | Tela |
|---|---|
| `screens/est-structure.html` | Contextbar (`BAR`) + aba Estrutura (`EST`) |
| `screens/fwk-frameworks.html` | Aba Frameworks (`FWK`) |
| `screens/mod-scale.html` | Modal `MOD.SCALE` · Escala/proficiência |
| `screens/pln-plans.html` | Aba Planos (`PLN`) |
| `screens/mod-browser.html` | Modal `MOD.BROWSER` · Procurar em frameworks |
| `screens/mod-links.html` | Modal `MOD.LINKS` · Cursos e atividades |
| `screens/mod-related.html` | Modal `MOD.RELATED` · Competências referenciadas |
| `screens/mod-rule.html` | Modal `MOD.RULE` · Regra de conclusão |
| `screens/mod-participants.html` | Modal `MOD.PART` (+ `MOD.COHORT`/`MOD.ROLES`) |
| `screens/mod-enrolmethods.html` | Modal `MOD.ENROL` · Métodos de inscrição (nova 4ª aba de `MOD.PART`) |
| `screens/mod-delplans.html` | Modal `MOD.DELPLANS` · Excluir template com planos |

Todas as superfícies da Central estão cobertas (as-is + to-be). Próximo: **Camada 3** —
alinhar os tokens ao Moodle DS e re-skinar os painéis to-be.

## Mapa de Campos (`maps/`)
Inventário **as-is** por superfície: cada elemento com **ID estável**, rótulo (ou `[sem rótulo]`),
tipo, **origem** (`mustache:linha` ou módulo `amd`), dados e regra de negócio. Resolve o
"campo sem rótulo / não acho no código" — referência no repo (não precisa ir pro Claude Design).

| Arquivo | Superfície |
|---|---|
| `maps/bar-contextbar.md` | `BAR` · Contextbar |
| `maps/est-structure.md` | `EST` · Aba Estrutura (+ nó da árvore) |
| `maps/fwk-frameworks.md` | `FWK` · Aba Frameworks (+ linha de framework) |
| `maps/pln-plans.md` | `PLN` · Aba Planos (templates + competências) |
| `maps/mod-browser.md` | `MOD.BROWSER` · Navegador de competências |
| `maps/mod-links.md` | `MOD.LINKS` · Vínculos curso↔atividade |
| `maps/mod-related.md` | `MOD.RELATED` · Competências referenciadas |
| `maps/mod-rule.md` | `MOD.RULE` · Regra de conclusão |
| `maps/mod-scale.md` | `MOD.SCALE` · Escala/proficiência do framework |
| `maps/mod-participants.md` | `MOD.PART` · Participantes (Coortes/Usuários/Papéis) |
| `maps/mod-enrolmethods.md` | `MOD.ENROL` · Métodos de inscrição (to-be — proposta) |
| `maps/mod-delplans.md` | `MOD.DELPLANS` · Excluir template com planos |
| `maps/mod-usage.md` | `MOD.USAGE` · Onde a competência é usada (**sem tela** — ver o mapa) |

### Convenção de IDs
Formato `PREFIXO-SECAO-NN`. Prefixos: `BAR` (contextbar), `EST`/`FWK`/`PLN` (abas),
`MOD.{BROWSER,LINKS,RELATED,RULE,COHORT,ROLES,PART,ENROL,SCALE,DELPLANS,USAGE}` (modais). Recebe ID todo elemento
interativo e toda região estática com significado (headings, empty states, contadores);
wrappers puros de layout não. IDs são estáveis — não mudam ao reordenar a tela.

**Um elemento, um ID.** O gatilho pertence à superfície **onde ele mora** — o mapa do modal o
**referencia**, não emite um segundo ID para ele. Ex.: os contadores que abrem o `MOD.USAGE` são
`EST-DETAIL-COURSES/-ACTIVITIES/-PLANS` e continuam no `est-structure.md`.

## Como sincronizar com o Claude Design
A ferramenta **DesignSync** lê e escreve o projeto (validado nesta sessão):
1. `list_projects` para achar o `projectId`.
2. `finalize_plan` (exige `writes` **e** `deletes`, mesmo `[]`) apontando `localDir` para esta pasta →
   retorna um `planId`.
3. `write_files` com `localPath` (relativo ao `localDir`); o conteúdo nunca entra no contexto do modelo.

Os `.html` de `screens/` e dos componentes sobem como cards; os `.md` de `maps/` ficam só no repo.
Alternativa: terminal `claude` interativo com `/design-login`, ou importar pela UI do Claude Design.

## Alinhamento ao Moodle DS (Camada 3)
Ver [`moodle-ds-alignment.md`](moodle-ds-alignment.md): boas práticas capturadas do
`moodlehq/design-system` + Component Library, mapa `MDS → Boost/Bootstrap → React do MDS (Moodle 5.3 LTS)`, e a
tabela de **divergências** da interpretação anterior. `tokens.html`, `states.html` e os **8 componentes** já
refletem isso (nomes legados viram aliases `--mds-*`). Os `screens/` foram re-skinados (to-be em tokens MDS,
as-is intocado) — **Camada 3 completa**.

## Mapeamento para código
- Cada componente → um partial **Mustache** + estilos **SCSS (Boost)**; os tokens deste kit → variáveis SCSS.
- Os modais usam `core_form\dynamic_form` via `core_form/modalform`; listas/árvore/picker usam `core/ajax`
  com **paginação server-side** e **lazy-load** (ver `../admin-redesign.md`, seção 9.5).
