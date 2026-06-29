# Mapa de Campos — `MOD.SCALE` · Escala/proficiência do framework (as-is)

Linhas inline de escala-proficiência de um framework: uma linha por valor da escala com
um radio "padrão" e um checkbox "proficiente". Renderizado client-side dentro do form de
criar/editar framework.

- **Mustache:** [`templates/central/framework_scaleconfig.mustache`](../../../templates/central/framework_scaleconfig.mustache)
- **AMD:** [`amd/src/central/framework_scaleconfig.js`](../../../amd/src/central/framework_scaleconfig.js)
- **To-be no DS:** parcial em `form-section.html`; tabela de escala é candidata a card.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.SCALE-HEAD` | Valor da escala · Padrão · Proficiente | cabeçalho | `framework_scaleconfig.mustache:34-38` | str `central_frameworks_scalevalue`/`scaledefault`/`scaleproficient` | — |
| `MOD.SCALE-ROW` | nome do valor | linha | `framework_scaleconfig.mustache:40` | `data-value="{id}"` | uma por valor da escala |
| `MOD.SCALE-DEFAULT` | `[só aria-label]` | radio | `framework_scaleconfig.mustache:43` | `name="dimensions-scaledefault"`, `data-role="default"` | exatamente um padrão por escala |
| `MOD.SCALE-PROFICIENT` | `[só aria-label]` | checkbox | `framework_scaleconfig.mustache:47` | `data-role="proficient"` | um ou mais proficientes |
