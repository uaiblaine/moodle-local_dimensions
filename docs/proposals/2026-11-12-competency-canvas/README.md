# Competency canvas: core today, the October study and the chosen line (2026-11-12)

The mockups of the competency design round, exported from the design canvas they were drawn on.
The live canvas is a private Claude artifact, "Competências: core, estudo de outubro e linha
escolhida" (https://claude.ai/artifact/8evrzvCm3fTMaUd8N7tf6e); this folder is the copy of its
version `1794534415-52f0`, taken on 2026-11-13. The mockup copy is in Brazilian Portuguese, the
language the round was held in.

## What is here

`project/` holds the canvas exactly as published: 76 artboards, three shared stylesheets and the
index.

- `canvas.json`: the index. Each artboard's title, page and frame on the canvas, plus the canvas
  notes.
- `lines.css`, `flow.css`, `dim.css`: the shared look of the "Nov" boards, of the "Fluxo" boards
  and of the "Out" boards.
- `*.dc.html`: one file per artboard.

The canvas has three pages:

| Page | Boards | Content |
|---|---|---|
| Comparativo por tela | 65 | Screen by screen: a brief (`Ficha01`-`Ficha13`), the core page as it is today (`Core*`), the October study (`Out*`) and the November line (`T*`, `P*`, and the three candidate lines `Main`/`A*` notebook, `B*` atlas, `C*` portfolio) |
| Terceira abordagem: Fluxo | 8 | `D*`: one workspace per persona (learner: today, record evidence, map; teacher: read, class, plan) |
| Acesso e papéis | 3 | `MapaAluno`, `MapaEquipe`, `Papeis`: who reaches which screen, and what each plugin of the family does |

`OutHubNodes.dc.html` ("Out · R2 · Hub do local_dimensions em nós (React Flow)") is the mockup the
Competency hub v2 (`hubv2.php`, `js/esm/src/hubv2`) was built from.

## Reading the files

An artboard is a Design Component page: it loads `./support.js`, the canvas runtime, which is not
part of this copy, so a `.dc.html` file opened from disk shows unstyled markup at best. Open the
artifact to see the boards rendered; read the files here for the markup, the copy and the
structure of a screen.

The ten `Core*` boards show a screenshot of the core page through an `/_blob/<id>` address of the
artifact's asset store. Those images are not in this copy; the board's title and its `<code>` line
name the core page they captured.

## Status

A design record, not a specification. Built so far from it: the Competency hub v2. The choice
between the lines is the owner's and is not recorded here.
