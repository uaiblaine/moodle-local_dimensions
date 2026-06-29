# Mapa de Campos — `FWK` · Aba Frameworks (as-is)

Lista os frameworks do contexto resolvido com ações nativas de gestão. O seletor
Sistema/Categoria vem da contextbar (`BAR`).

- **Mustache:** [`templates/central/frameworks.mustache`](../../../templates/central/frameworks.mustache), [`templates/central/frameworks_row.mustache`](../../../templates/central/frameworks_row.mustache)
- **AMD:** [`amd/src/central/frameworks.js`](../../../amd/src/central/frameworks.js)
- **To-be no DS:** sem componente dedicado (lista simples) — candidato a card.

## Cabeçalho

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-EMPTY-CAT` | "Selecione uma categoria…" | empty-state | `frameworks.mustache:48` | `needscategoryselection` | bloqueia a aba |
| `FWK-COUNT` | "Frameworks: N" | contador | `frameworks.mustache:53` | `frameworkcount` | — |
| `FWK-NEW` | Novo framework | botão | `frameworks.mustache:57` | `data-action="new"` | só `canmanage`; `btn-primary` |
| `FWK-SHOWHIDDEN` | Mostrar frameworks ocultos | switch | `frameworks.mustache:62-67` | `data-action="toggle-hidden"` | `showhidden` |
| `FWK-EMPTY` | "Sem frameworks" | empty-state | `frameworks.mustache:81` | str `central_frameworks_none` | — |

## Linha de framework (`frameworks_row`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-ROW` | — | linha | `frameworks_row.mustache:37` | `data-framework="{id}"` | carrega `data-name/count/visible/deletable` |
| `FWK-ROW-NAME` | nome | texto | `frameworks_row.mustache:42` | `shortname` | `fw-bold` + idnumber + "N competências" |
| `FWK-ROW-HIDDEN` | "Oculto" | badge | `frameworks_row.mustache:45` | `^visible` | só quando invisível |
| `FWK-ROW-VIS` | `[só title/sr]` | botão ícone | `frameworks_row.mustache:49` | `data-action="visibility"` | `fa-eye`/`fa-eye-slash`; alterna visibilidade |
| `FWK-ROW-EDIT` | Editar | botão | `frameworks_row.mustache:54` | `data-action="edit"` | abre form (com `MOD.SCALE` embutido) |
| `FWK-ROW-DUP` | Duplicar | botão | `frameworks_row.mustache:57` | `data-action="duplicate"` | — |
| `FWK-ROW-DEL` | Excluir | botão | `frameworks_row.mustache:60` | `data-action="delete"` | `btn-outline-danger`; só se `deletable` |
