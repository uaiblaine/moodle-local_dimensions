# Mapa de Campos — `BAR` · Contextbar (as-is)

Seletor de contexto compartilhado pelas abas Estrutura e Planos. Sempre renderizado
(oculto em modo sistema), troca de contexto 100% client-side.

- **Mustache:** [`templates/central/contextbar.mustache`](../../../templates/central/contextbar.mustache)
- **AMD:** [`amd/src/central/context.js`](../../../amd/src/central/context.js)
- **To-be no DS:** `hierarchy-nav.html` (propõe trilha adaptativa + contexto em card — **diverge** do as-is).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-CTX-LABEL` | Contexto | label/heading | `contextbar.mustache:58` | str `managecompetencies_context` | rótulo do grupo de botões |
| `BAR-CTX-01` | Sistema | botão toggle | `contextbar.mustache:60` | `data-context="system"` | `btn-primary` quando `issystem`; ícone `fa-globe` |
| `BAR-CTX-02` | Categoria do curso | botão toggle | `contextbar.mustache:63` | `data-context="coursecat"` | `btn-primary` quando `iscoursecat`; ícone `fa-folder-open-o` |
| `BAR-CAT-LABEL` | Categoria | label | `contextbar.mustache:70` | str `managecompetencies_category` | `for` aponta o select abaixo |
| `BAR-CAT-01` | Categoria (select) | select | `contextbar.mustache:73` | `categoryoptions` | wrapper `hidden` se `^iscoursecat`; opção mostra `nome (frameworkcount)`; `data-frameworkcount`/`data-templatecount` por opção |
| `BAR-CAT-PLACEHOLDER` | "Selecione…" | option | `contextbar.mustache:74` | `value="0"` | placeholder; valor 0 = sem categoria |
| `BAR-COUNT-01` | `[rótulo alterna]` | contador | `contextbar.mustache:81-86` | `selectedframeworkcount` / `selectedtemplatecount` | troca texto "frameworks"/"planos" via `data-mode` conforme a aba; `hidden` se `needscategory` |

**Regras de negócio**
- A barra carrega ambos os contadores (`data-systemframeworkcount`, `data-systemtemplatecount`) para alternar sem round-trip.
- `data-activemode="structure"` define qual contagem o `BAR-COUNT-01` mostra inicialmente.
