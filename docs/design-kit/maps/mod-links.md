# Mapa de Campos — `MOD.LINKS` · Modal vínculos curso↔atividade (as-is)

Casca do modal "Cursos e atividades". As linhas de curso e as linhas de atividade por
curso são **renderizadas client-side**; a casca traz o autocomplete de adicionar curso e
as regiões vazio/carregar-mais.

- **Mustache:** [`templates/central/competency_links.mustache`](../../../templates/central/competency_links.mustache)
- **AMD:** [`amd/src/central/competency_links.js`](../../../amd/src/central/competency_links.js), [`amd/src/central/course_datasource.js`](../../../amd/src/central/course_datasource.js)
- **To-be no DS:** ainda **não há** componente dedicado — candidato a novo card.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-HIDDENFW` | aviso framework oculto | alerta | `competency_links.mustache:33` | `data-region="hiddenframework"` | `hidden` até o JS detectar framework oculto; `alert-warning` |
| `MOD.LINKS-ADD-LABEL` | Adicionar curso | label | `competency_links.mustache:37` | str `central_links_addcourse` | — |
| `MOD.LINKS-ADD` | Adicionar curso (autocomplete) | select/autocomplete | `competency_links.mustache:40` | `data-region="course-add"` | `data-exclude` lido via `dataset` a cada busca; datasource `course_datasource.js` |
| `MOD.LINKS-ROWS` | `[sem rótulo]` | contêiner-JS | `competency_links.mustache:46` | `data-region="course-rows"` | linhas de curso (+ atividades) injetadas por `competency_links.js` |
| `MOD.LINKS-EMPTY` | "Nenhum curso vinculado" | empty-state | `competency_links.mustache:47` | str `central_links_nocourses` | `hidden` até zerar a lista |
| `MOD.LINKS-LOADMORE` | Carregar mais | botão | `competency_links.mustache:50-53` | `data-action="loadmore"` | dentro de `loadmore-wrap`, `hidden` por padrão |

**Injetado via JS (detalhar ao inventariar `competency_links.js`):**
linha de curso (nome + ações), linha de atividade por curso (vincular/desvincular) →
`MOD.LINKS-COURSEROW-*`, `MOD.LINKS-ACTIVITYROW-*`.
