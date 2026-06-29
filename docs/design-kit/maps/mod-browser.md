# Mapa de Campos — `MOD.BROWSER` · Modal navegador de competências (as-is)

Corpo do modal "Procurar em frameworks": seletor de framework + filtro client-side +
toggle de caminhos. As linhas de competência (checkbox) são **injetadas via JS** (chama
web services de leitura do core).

- **Mustache:** [`templates/central/competency_browser.mustache`](../../../templates/central/competency_browser.mustache)
- **AMD:** [`amd/src/central/competency_browser.js`](../../../amd/src/central/competency_browser.js), [`amd/src/central/competency_datasource.js`](../../../amd/src/central/competency_datasource.js)
- **To-be no DS:** `paginated-picker.html` (propõe busca server-side **paginada** — **diverge** do as-is, que é filtro client-side sobre lista carregada).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.BROWSER-FW-LABEL` | Framework | label | `competency_browser.mustache:40` | str `central_browseframeworks_framework` | — |
| `MOD.BROWSER-FW` | Framework (select) | select | `competency_browser.mustache:43` | `data-region="framework"` | troca de framework recarrega a lista via JS |
| `MOD.BROWSER-FILTER` | `[placeholder]` | input texto | `competency_browser.mustache:50` | `data-region="filter"` | filtro client-side; placeholder `central_browseframeworks_filter` |
| `MOD.BROWSER-PATHTOGGLE` | Mostrar caminhos | switch | `competency_browser.mustache:53-57` | `data-region="path-toggle"` | exibe o caminho hierárquico em cada linha |
| `MOD.BROWSER-LIST` | `[sem rótulo]` | contêiner-JS | `competency_browser.mustache:59` | `data-region="competency-list"` | linhas de checkbox injetadas por `competency_browser.js` |
| `MOD.BROWSER-EMPTY` | "Sem frameworks" | empty-state | `competency_browser.mustache:62` | str `central_browseframeworks_noframeworks` | — |

**Injetado via JS (detalhar ao inventariar `competency_browser.js`):**
linha de competência (checkbox + nome + caminho opcional) → `MOD.BROWSER-ROW-*`.
