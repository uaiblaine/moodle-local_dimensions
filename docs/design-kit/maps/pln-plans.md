# Mapa de Campos — `PLN` · Aba Planos (as-is)

Master-detail: lista de templates de plano + competências do template selecionado
(cross-framework). Inclui filtro "planos que contêm a competência X".

- **Mustache:** [`templates/central/plans.mustache`](../../../templates/central/plans.mustache)
- **AMD:** [`amd/src/central/plans.js`](../../../amd/src/central/plans.js)
- **To-be no DS:** `master-detail.html` (mesmo padrão árvore↔detalhe).

## Busca / cabeçalho

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-EMPTY-CAT` | "Selecione uma categoria…" | empty-state | `plans.mustache:68` | `needscategoryselection` | bloqueia a aba |
| `PLN-SEARCH` | Buscar competência | select/autocomplete | `plans.mustache:77` | `data-region="competency-search"` | filtra planos por competência (cross-framework) |
| `PLN-NEW` | Adicionar plano | botão | `plans.mustache:85` | `data-action="new-template"` | só `canmanage` |
| `PLN-FILTER-BADGE` | "Filtrado por: X" | badge | `plans.mustache:92` | `filteredbycompetency` | aparece quando há filtro ativo |
| `PLN-FILTER-CLEAR` | Limpar filtro | botão | `plans.mustache:93` | `data-action="clear-competency"` | — |

## Lista de templates (painel esquerdo)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-TPL-ROW` | nome do template | botão | `plans.mustache:105` | `data-action="select-template"` | ícone prancheta + badge de contagem; `active` se selecionado |
| `PLN-TPL-HIDDEN` | "Oculto" | badge | `plans.mustache:111` | `^visible` | — |

## Detalhe do plano (painel direito)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-DETAIL-TITLE` | nome do template | heading | `plans.mustache:121` | `selectedtemplatename` | — |
| `PLN-DETAIL-COUNT` | "Competências: N" | contador | `plans.mustache:122` | `competencycount` | — |
| `PLN-ADD` | Adicionar competência | select/autocomplete | `plans.mustache:130` | `data-region="competency-add"` | `data-exclude` = ids já no template |
| `PLN-BROWSE` | Procurar em frameworks | link | `plans.mustache:134` | `data-action="browse-frameworks"` | abre `MOD.BROWSER` |
| `PLN-COMP-ROW` | nome da competência | linha | `plans.mustache:143` | `data-competency="{id}"` | + badge `frameworktag` |
| `PLN-COMP-UP` | `[só aria-label]` | botão ícone | `plans.mustache:151` | `data-action="move-competency-up"` | `disabled` se `first` |
| `PLN-COMP-DOWN` | `[só aria-label]` | botão ícone | `plans.mustache:157` | `data-action="move-competency-down"` | `disabled` se `last` |
| `PLN-COMP-REMOVE` | `[só aria-label]` | botão ícone | `plans.mustache:163` | `data-action="remove-competency"` | `fa-times`, vermelho |
| `PLN-DETAIL-EMPTY` | "Sem competências" | empty-state | `plans.mustache:175` | str `nocompetencies` | `alert-warning` |
| `PLN-EDIT` | Editar | botão | `plans.mustache:179` | `data-action="edit-template"` | — |
| `PLN-DELETE` | Excluir | botão | `plans.mustache:183` | `data-action="delete-template"` | abre `MOD.DELPLANS` se houver planos |
| `PLN-PARTICIPANTS` | Participantes | botão | `plans.mustache:187` | `data-action="manage-participants"` | abre `MOD.PART` |

## Estados vazios

| ID | Rótulo | Origem | Regra / notas |
| --- | --- | --- | --- |
| `PLN-EMPTY-FILTERED` | "Nenhum plano com essa competência" | `plans.mustache:200` | quando filtrado e vazio |
| `PLN-EMPTY` | "Sem planos" | `plans.mustache:203` | sem filtro e vazio |
