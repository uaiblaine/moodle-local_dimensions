# Mapa de Campos — `EST` · Aba Estrutura (as-is)

Master-detail: seletor de framework + árvore de competências (esquerda) + painel de
detalhe (direita). Os nós da árvore vêm de `structure_node` (raízes server-side,
filhos lazy via JS).

- **Mustache:** [`templates/central/structure.mustache`](../../../templates/central/structure.mustache), [`templates/central/structure_node.mustache`](../../../templates/central/structure_node.mustache)
- **AMD:** [`amd/src/central/structure.js`](../../../amd/src/central/structure.js)
- **To-be no DS:** `master-detail.html` (chips ricos no detalhe — **diverge** do as-is, que tem campos simples).

## Cabeçalho e seletor

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-EMPTY-CAT` | "Selecione uma categoria…" | empty-state | `structure.mustache:72` | `needscategoryselection` | bloqueia a aba até escolher categoria |
| `EST-SHOWHIDDEN` | Mostrar frameworks ocultos | switch | `structure.mustache:76-81` | `data-action="toggle-hidden"` | `form-switch`; estado em `showhidden` |
| `EST-FW-LABEL` | Framework de competências | label | `structure.mustache:86` | str `competencyframework, tool_lp` | — |
| `EST-FW-SELECT` | Framework (select) | select | `structure.mustache:89` | `frameworks` | opção: `nome · idnumber · oculto` |
| `EST-FW-COUNT` | "Itens: N" | contador | `structure.mustache:95` | `competencycount` | — |
| `EST-ADDROOT` | Adicionar competência raiz | botão | `structure.mustache:99` | `data-action="addroot"` | só com `canmanage`; `btn-primary`, alinhado à direita |

## Árvore (painel esquerdo)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-TREE-PANE` | — | painel/card | `structure.mustache:107` | `data-region="tree-pane"` | wrapper (sem rótulo) |
| `EST-TREE` | — | contêiner | `structure.mustache:109` | `data-region="competency-tree"` | recebe os `EST-NODE-*` |
| `EST-TREE-LOADMORE` | Carregar mais | botão | `structure.mustache:114` | `data-region="root-loadmore"` | só se `hasmoreroots`; `data-offset`/`data-total` |

### Nó da árvore (`structure_node`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-NODE` | — | linha (wrapper) | `structure_node.mustache:44` | `data-node="{id}"` | recuo via `padding-left:{indent}px` |
| `EST-NODE-TOGGLE` | `[só aria-label]` | botão chevron | `structure_node.mustache:47` | `data-action="toggle"` | só se `haschildren`; `aria-expanded`; carrega filhos lazy |
| `EST-NODE-ROW` | nome da competência | botão | `structure_node.mustache:54` | `data-action="select"` | carrega `data-name/idnumber/taxonomy/courses/ruletype/...` para o detalhe |
| `EST-NODE-ICON` | `[sem rótulo]` | ícone | `structure_node.mustache:66` | — | `fa-folder-o` se `haschildren`, senão `fa-circle-o` |
| `EST-NODE-NAME` | nome | texto | `structure_node.mustache:67` | `shortname` | — |
| `EST-NODE-CHILDREN` | `[sem rótulo]` | contêiner-JS | `structure_node.mustache:70` | `data-children="{id}"` | preenchido por `structure.js` ao expandir |

## Detalhe (painel direito)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-DETAIL-EMPTY` | "Selecione um item para ver detalhes" | empty-state | `structure.mustache:126` | `data-region="detail-empty"` | visível até selecionar um nó |
| `EST-DETAIL-TAXONOMY` | `[sem rótulo]` | contêiner-JS | `structure.mustache:131` | `data-region="detail-taxonomy"` | preenchido via JS |
| `EST-DETAIL-TITLE` | `[sem rótulo]` | contêiner-JS | `structure.mustache:132` | `data-region="detail-title"` | título da competência |
| `EST-DETAIL-IDNUMBER` | `[sem rótulo]` | contêiner-JS (mono) | `structure.mustache:133` | `data-region="detail-idnumber"` | idnumber em fonte mono |
| `EST-DETAIL-COURSES` | `[sem rótulo]` | contêiner-JS | `structure.mustache:134` | `data-region="detail-courses"` | contagem de cursos |
| `EST-DETAIL-EDIT` | Editar | botão | `structure.mustache:137` | `data-action="edit"` | só `canmanage` |
| `EST-DETAIL-ADDCHILD` | Adicionar filha | botão | `structure.mustache:140` | `data-action="addchild"` | — |
| `EST-DETAIL-RULES` | (Regra de conclusão) | botão | `structure.mustache:143` | `data-action="rules"` | str `competencyrule, tool_lp`; abre `MOD.RULE` |
| `EST-DETAIL-LINKS` | Vínculos | botão | `structure.mustache:146` | `data-action="links"` | abre `MOD.LINKS` |
| `EST-DETAIL-MOVEUP` | `[só title/sr]` | botão ícone | `structure.mustache:149` | `data-action="moveup"` | rótulo só em `title` + `visually-hidden` |
| `EST-DETAIL-MOVEDOWN` | `[só title/sr]` | botão ícone | `structure.mustache:152` | `data-action="movedown"` | idem |
| `EST-DETAIL-DELETE` | Excluir | botão | `structure.mustache:155` | `data-action="delete"` | `btn-outline-danger` |

## Estados vazios

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-EMPTY-COMP` | "Sem competências" | empty-state | `structure.mustache:168` | str `nocompetencies` | framework sem competências |
| `EST-EMPTY-FW` | "Sem frameworks" | empty-state | `structure.mustache:173` | str `noframeworks` | contexto sem frameworks |
