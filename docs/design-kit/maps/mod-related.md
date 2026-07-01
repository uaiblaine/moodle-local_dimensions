# Mapa de Campos — `MOD.RELATED` · Modal competências referenciadas (as-is)

Casca do modal "Competências referenciadas", aberto pelo botão **⇄** no detalhe da aba
Estrutura (só `canmanage`). As linhas de competência referenciada são **renderizadas
client-side**; a casca traz o autocomplete de adicionar (framework-scoped) e a região de
estado vazio. Related é **same-framework only** (constraint do core
`related_competency::validate_relatedcompetencyid` → `share_same_framework`) e **simétrico**
(`api::list_related_competencies` faz UNION dos dois sentidos), então adicionar/remover afeta
os dois lados.

- **Mustache:** [`templates/central/related_competencies.mustache`](../../../templates/central/related_competencies.mustache)
- **AMD:** [`amd/src/central/related_competencies.js`](../../../amd/src/central/related_competencies.js), [`amd/src/central/related_datasource.js`](../../../amd/src/central/related_datasource.js)
- **WS:** `local_dimensions_list_related_competencies` (listar, com caminho de ancestrais), `local_dimensions_search_structure` (picker), core `core_competency_{add,remove}_related_competency` (escrever)
- **To-be no DS:** [`screens/mod-related.html`](../screens/mod-related.html) (legado YUI ↔ modal do hub).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-ACTION` | Competências referenciadas | botão (gatilho) | `structure.mustache` (detalhe, sob `{{#canmanage}}`) | `data-action="related"` | ícone `fa-exchange`; abre o modal via `related_competencies.open({competencyid, competencyname, frameworkid})` em `structure.js` |
| `MOD.RELATED-TITLE` | Competências referenciadas — {nome} | título do modal | `related_competencies.js` (`open`) | str `central_related_title` | `$a` = nome da competência |
| `MOD.RELATED-ADDLABEL` | Adicionar competência referenciada | label | `related_competencies.mustache:36-38` | str `central_related_add` | `for="local-dimensions-related-add"` |
| `MOD.RELATED-ADD` | Adicionar (autocomplete) | select/autocomplete | `related_competencies.mustache:39-44` | `data-region="related-add"`, `data-frameworkid`, `data-exclude` | datasource `related_datasource.js` → WS `search_structure` (mesmo framework); `enhance` no `ModalEvents.shown`; exclui a própria + já-referenciadas (lido fresh via `dataset`) |
| `MOD.RELATED-ROWS` | `[sem rótulo]` | contêiner-JS | `related_competencies.mustache:46` | `data-region="related-list"` | linhas injetadas por `related_competencies.js` (`makeRow`): nome + nº id (mono) + caminho de ancestrais |
| `MOD.RELATED-ROW-REMOVE` | remover | botão (por linha) | `related_competencies.js` (`makeRow`) | `data-action="remove-related"`, `data-relatedid` na linha | confirm `deleteCancelPromise` (str `central_related_remove` / `..._remove_confirm`) → `core_competency_remove_related_competency`; simétrico |
| `MOD.RELATED-EMPTY` | "Nenhuma competência referenciada ainda." | empty-state | `related_competencies.mustache:47-49` | str `central_related_empty` | `hidden` até a lista zerar |
| `MOD.RELATED-TOAST` | `[sem rótulo]` | feedback | `related_competencies.js` (`ModalEvents.shown`) | `addToastRegion(modal.getBody())` | toast in-modal acima do dialog (strs `central_related_added` / `..._removed`) + flash da linha no add |

**Fluxo (via JS `related_competencies.js`):** abrir → `loadRelations` (WS `list_related_competencies`,
reconstrói o exclude-set = própria + relacionadas) → adicionar (pick no autocomplete →
`core_competency_add_related_competency` → re-fetch da lista + reset do picker) → remover
(confirm → `core_competency_remove_related_competency` → tira a linha + toast + estado vazio).
