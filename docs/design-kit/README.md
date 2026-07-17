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
| `toast.html` | Fundações | Toast de confirmação da casa (success/info/warning/danger) + a região hospedada no corpo do modal (`addToastRegion`, z-index) e o par com o flash. |
| `modal-shell.html` | Shell | Cabeçalho + corpo + rodapé de modal (base de todo modal `dynamic_form`) — as-is ↔ to-be (D2). |
| `sticky-footer.html` | Shell | As **3 variantes reais** + o invariante: o hub constrói **17** modais (4 `ModalForm` + 13 `Modal*.create`); o rodapé alcança **10** direto, é a **única** porta de **7** e **8** dependem dele. |
| `form-section.html` | Formulário | Seção com título + descrição (explicação) e linhas de campo (texto, select, colorpicker). |
| `image-dropzone.html` | Formulário | Anexo de imagem padrão Moodle (arrasta e solta) — vazio e com arquivo. |
| `hierarchy-nav.html` | Navegação | Seletor de contexto (Sistema/Categoria + contador), trilha adaptativa, abas Estrutura/Planos. |
| `master-detail.html` | Dados | Árvore de competências + painel de detalhe (chips do cabeçalho + três métricas). |
| `paginated-picker.html` | Dados | Busca server-side cross-framework + resultados AJAX + aviso de overflow quando passa de uma página; chip da estrutura de origem. |
| `cohort-assign.html` | Dados | Atribuição de plano a coortes/usuários (estilo gestão de grupos do mtube) + sync. |

> Estes componentes expressam a **visão to-be** e divergem em pontos da UI shipada hoje
> (ex.: trilha adaptativa e chips ricos que ainda não existem nos mustaches). As telas
> `screens/` reconciliam isso mostrando as-is e to-be lado a lado.

> **Exceção — `modal-shell.html`.** É o único componente com painel as-is: o as-is do shell (o chip
> real do `.btn-close`, o slot de link no cabeçalho, a bifurcação do rodapé) não existe em nenhuma
> tela, e a proposta D2 é ilegível sem ver o cabeçalho de hoje ao lado do proposto. O shell é o único
> componente cujo as-is é disputado.

## Telas as-is ↔ to-be (`screens/`)
Réplica de cada tela da Central em **dois painéis lado a lado** — **as-is** (fiel ao output
shipado, base limpa de revisão) e **to-be** (proposta) — com **badge de ID** em cada elemento.
Formato `@dsCard`, grupo "Telas (as-is ↔ to-be)" no índice do DS.

| Arquivo | Tela |
|---|---|
| `screens/est-structure.html` | Contextbar (`BAR`) + aba Estrutura (`EST`) |
| `screens/fwk-frameworks.html` | Aba Estruturas (`FWK`) |
| `screens/mod-scale.html` | Modal `MOD.SCALE` · Escala/proficiência |
| `screens/pln-plans.html` | Aba Planos (`PLN`) |
| `screens/mod-browser.html` | Modal `MOD.BROWSER` · Procurar em estruturas |
| `screens/mod-links.html` | Modal `MOD.LINKS` · Cursos e atividades |
| `screens/mod-related.html` | Modal `MOD.RELATED` · Competências referenciadas |
| `screens/mod-rule.html` | Modal `MOD.RULE` · Regra de conclusão |
| `screens/mod-participants.html` | Modal `MOD.PART` (+ `MOD.COHORT`/`MOD.ROLES`) |
| `screens/mod-enrolmethods.html` | Modal `MOD.ENROL` · Métodos de inscrição (nova 4ª aba de `MOD.PART`) |
| `screens/mod-delplans.html` | Modal `MOD.DELPLANS` · Excluir template com planos |
| `screens/mod-detail.html` | Modal `MOD.DETAIL` · O card é o diálogo (**painel único as-is** — nada proposto) |

**Cobertura (2026-07-15).** As 15 superfícies listadas abaixo têm **mapa** — o que inclui o
`MOD.USAGE`, que até esta rodada **não tinha nenhum**, apesar de a linha anterior daqui já declarar
cobertura total. A afirmação valia para as superfícies que alguém tinha lembrado de listar, não para
o hub. Dois modais têm mapa e **não têm tela**, de propósito: `MOD.USAGE` e `MOD.MOVETO` não carregam
decisão de design (uma lista `<li>`; um `<label>` + `<select>`), e desenhá-los só acrescentaria
superfície ao kit. O `MOD.DETAIL` tem tela de **um painel só** — é as-is puro, sem to-be. Onde a
cobertura **não** chega está registrado em "Pontos cegos conhecidos", no fim deste arquivo.

## Mapa de Campos (`maps/`)
Inventário **as-is** por superfície: cada elemento com **ID estável**, rótulo (ou `[sem rótulo]`),
tipo, **origem** (`mustache:linha` ou módulo `amd`), dados e regra de negócio. Resolve o
"campo sem rótulo / não acho no código" — referência no repo (não precisa ir pro Claude Design).

| Arquivo | Superfície |
|---|---|
| `maps/bar-contextbar.md` | `BAR` · Contextbar |
| `maps/est-structure.md` | `EST` · Aba Estrutura (+ nó da árvore) |
| `maps/fwk-frameworks.md` | `FWK` · Aba Estruturas (+ card de estrutura) |
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
| `maps/mod-moveto.md` | `MOD.MOVETO` · Mover para posição — um template, duas abas (**sem tela**) |
| `maps/mod-detail.md` | **`MOD.DETAIL`** · O card da competência como diálogo (headless). O **template** se chama `structure_related_modal.mustache` — quem grepar por esse nome cai aqui; os arquivos (`maps/mod-detail.md`, `screens/mod-detail.html`) levam o **ID**, que o `pln-plans.md` já tinha cunhado. O nome do template é que envelheceu, não o ID |

### Convenção de IDs
Formato `PREFIXO-SECAO-NN`. Prefixos: `BAR` (contextbar), `EST`/`FWK`/`PLN` (abas),
`MOD.{BROWSER,LINKS,RELATED,RULE,COHORT,ROLES,PART,ENROL,SCALE,DELPLANS,USAGE,MOVETO,DETAIL}` (modais). Recebe ID todo elemento
interativo e toda região estática com significado (headings, empty states, contadores);
wrappers puros de layout não. IDs são estáveis — não mudam ao reordenar a tela.

**Um elemento, um ID.** O gatilho pertence à superfície **onde ele mora**; o mapa do modal
**referencia** em vez de cunhar um segundo ID (padrão: `MOD.DELPLANS` ← `PLN-DELETE`). Cunhar um ID
novo para um elemento já mapeado quebra a estabilidade que a convenção existe para garantir — e
deixa a referência do mapa de origem apontando para o nada. Ex.: os contadores que abrem o
`MOD.USAGE` são `EST-DETAIL-COURSES/-ACTIVITIES/-PLANS` e continuam no `est-structure.md`; o
`MOD.DETAIL` mantém o ID que o `pln-plans.md` cunhou, em vez de virar `MOD.STRUCTRELATED` pelo nome
do template.

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

## Estado do kit (2026-07-15)
Camada 3 fechada. O kit foi **ressincronizado contra o `f84d30a`**: todo mapa foi re-derivado do
código, não corrigido ponto a ponto. As melhorias inspiradas no `format_mtube` (`IMP-*`, `D2`, `D5`)
entram como **to-be** nos mapas e telas; o backlog de código que elas geram vive na **spec do
redesign** — que é local, fora deste repo (o `.gitignore` exclui `docs/*` menos esta pasta).

Por que as refs merecem confiança agora, e não antes: no resync, **7 mapas** tinham refs quebradas —
23/23 (`est-structure`), 21/21 (`pln-plans`), 12/12 (`fwk-frameworks`), 12/24 (`mod-participants`),
4/6 (`mod-browser`), 4/4 (`mod-related`) e 3/3 (`mod-delplans`, órfãs). Outros três estavam **inteiros**
(`mod-links` 6/6, `mod-rule` 11/11, `mod-scale` 4/4) e mesmo assim descreviam a superfície errado.
**Todo** mapa tinha zero ou quase zero refs de **JS**, e em dois (`mod-browser`, `mod-participants`)
havia refs que caíam num controle **real de outro ID** — o pior tipo, porque lê como correto até
alguém conferir.

## Pontos cegos conhecidos
As **duas** lacunas que a auditoria expôs — ambas **fechadas** (2026-07-17):

1. ✅ **~~Nenhum corpo de `dynamic_form` é mapeado~~ — FECHADA.** Os quatro corpos ganharam mapa em
   [`maps/mod-forms.md`](maps/mod-forms.md) (fidelidade cheia: `FORM-FWK`/`FORM-COMP`/`FORM-TPL`/`FORM-IMP`,
   inventário de campos, gating, validação e controles de design), e as IDs provisórias do `MOD.SCALE`
   migraram para lá (`FORM-FWK-SCALE-*`). No mesmo passo, o `structure_related_modal` — o único modal
   sem mapa — ganhou [`maps/mod-structrelated.md`](maps/mod-structrelated.md). Os mapas do kit agora
   cobrem **toda** superfície administrativa.
2. ✅ **~~O toast virou o padrão de confirmação da casa e não está em nenhum componente~~ — FECHADA.**
   Agora modelado em [`toast.html`](toast.html): os quatro tipos (success/info/warning/danger), a
   **região hospedada no corpo do modal** (`addToastRegion(modal.getBody()[0])` no `ModalEvents.shown`,
   porque a `.toast-wrapper` da página é `z-index:1051` e o modal é `1055` — o toast cairia **atrás**
   dele; o core remove a região sozinho no fechar) e o **par com o flash** para mudanças in-place.
   Ligado em `competency_links`, `participants_manager`, `related_competencies` e `frameworks_export`.
   No mesmo passo, o `MOD.STRUCTREL` ganhou tela em [`screens/mod-structrelated.html`](screens/mod-structrelated.html).

## Mapeamento para código
- Cada componente → um partial **Mustache** + estilos **SCSS (Boost)**; os tokens deste kit → variáveis SCSS.
- Os modais usam `core_form\dynamic_form` via `core_form/modalform`; listas/árvore/picker usam `core/ajax`
  com **paginação server-side** e **lazy-load** (ver a spec do redesign, seção 9.5 — local, não versionada).
