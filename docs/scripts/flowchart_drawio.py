"""Buat docs/flowchart.drawio dari blok Mermaid di docs/flowchart.md (sumber tunggal).

Jalankan dari root repo setelah mengubah flowchart.md:
    python docs/scripts/flowchart_drawio.py
Mendukung subset Mermaid: ([mulai]), [langkah], {keputusan}, [(sistem)], [[alur lain]], A --> B, A -- label --> B, A & B --> C.
Tata letak dibuat otomatis (atas ke bawah); panah balik digambar putus-putus.
"""
import math
import re
import sys
from html import escape
from pathlib import Path

OPEN, CLOSE = '([{', ')]}'


def split_statement(s):
    """Pisahkan pernyataan menjadi [grup node, label, grup node, ...] (label None jika tanpa label)."""
    parts, buf, depth, i = [], '', 0, 0
    while i < len(s):
        ch = s[i]
        if ch in OPEN:
            depth += 1
        elif ch in CLOSE:
            depth -= 1
        if depth == 0 and s.startswith('-->', i):
            parts += [buf.strip(), None]
            buf, i = '', i + 3
            continue
        if depth == 0 and s.startswith('-- ', i):
            j = s.index('-->', i + 3)
            parts += [buf.strip(), s[i + 3:j].strip()]
            buf, i = '', j + 3
            continue
        buf += ch
        i += 1
    parts.append(buf.strip())
    return parts


def split_group(g):
    out, buf, depth = [], '', 0
    for ch in g:
        if ch in OPEN:
            depth += 1
        elif ch in CLOSE:
            depth -= 1
        if ch == '&' and depth == 0:
            out.append(buf.strip())
            buf = ''
        else:
            buf += ch
    out.append(buf.strip())
    return [x for x in out if x]


def parse_node(expr):
    m = re.match(r'^(\w+)(.*)$', expr.strip(), re.S)
    nid, rest = m.group(1), m.group(2).strip()
    if not rest:
        return nid, None, None
    for o, c, kind in (('([', '])', 'terminal'), ('[(', ')]', 'data'), ('[[', ']]', 'link'), ('{', '}', 'decision'), ('[', ']', 'process')):
        if rest.startswith(o) and rest.endswith(c):
            return nid, kind, rest[len(o):-len(c)].strip()
    raise ValueError(f'Bentuk node tidak dikenal: {expr}')


def parse(src):
    nodes, order, edges = {}, [], []
    for line in src.strip().splitlines():
        line = line.strip()
        if not line:
            continue
        parts = split_statement(line)
        groups = []
        for k in range(0, len(parts), 2):
            ids = []
            for expr in split_group(parts[k]):
                nid, kind, label = parse_node(expr)
                if nid not in nodes:
                    nodes[nid] = {'kind': 'process', 'label': nid}
                    order.append(nid)
                if kind:
                    nodes[nid] = {'kind': kind, 'label': label}
                ids.append(nid)
            groups.append(ids)
        for k in range(len(groups) - 1):
            label = parts[2 * k + 1]
            for a in groups[k]:
                for b in groups[k + 1]:
                    edges.append((a, b, label))
    return nodes, order, edges


def size(node):
    kind, text = node['kind'], node['label']
    n = len(text)
    if kind == 'terminal':
        return 130, 46
    if kind == 'decision':
        w = 210 if n < 40 else 250
        per = 15 if w == 210 else 19
        lines = max(1, math.ceil(n / per))
        return w, max(90, 20 * lines + 50)
    w = 250
    lines = max(1, math.ceil(n / 36))
    h = max(46, 17 * lines + 20)
    if kind == 'data':
        h += 14
    return w, h


def layout(nodes, order, edges):
    succ = {n: [] for n in order}
    indeg = {n: 0 for n in order}
    for a, b, _ in edges:
        succ[a].append(b)
        indeg[b] += 1
    # DFS untuk menandai panah balik (siklus).
    back, state, disc = set(), {}, []

    def dfs(u):
        state[u] = 1
        disc.append(u)
        for v in succ[u]:
            if state.get(v) == 1:
                back.add((u, v))
            elif v not in state:
                dfs(v)
        state[u] = 2

    roots = [order[0]] + [n for n in order if indeg[n] == 0 and n != order[0]]
    for r in roots + order:
        if r not in state:
            dfs(r)
    fwd = [(a, b) for a, b, _ in edges if (a, b) not in back and a != b]
    # Lapisan = jalur terpanjang pada graf tanpa panah balik.
    layer = {n: 0 for n in order}
    for _ in range(len(order)):
        changed = False
        for a, b in fwd:
            if layer[b] < layer[a] + 1:
                layer[b] = layer[a] + 1
                changed = True
        if not changed:
            break
    layers = {}
    for n in disc:
        layers.setdefault(layer[n], []).append(n)
    dims = {n: size(nodes[n]) for n in order}
    preds = {n: [a for a, b in fwd if b == n] for n in order}
    gap_x, gap_y = 60, 70
    x, y = {}, {}
    cy = 40
    for li in sorted(layers):
        row = layers[li]
        # Posisi ideal = rata-rata tengah induk; lalu rapikan agar tidak bertumpuk.
        def ideal(n):
            ps = [p for p in preds[n] if p in x]
            return sum(x[p] + dims[p][0] / 2 for p in ps) / len(ps) if ps else None

        known = [(ideal(n), i, n) for i, n in enumerate(row)]
        fallback = 0
        items = []
        for val, i, n in known:
            if val is None:
                val = fallback + i * (260 + gap_x)
            items.append((val, i, n))
        items.sort()
        cursor = -1e9
        for val, _, n in items:
            left = max(val - dims[n][0] / 2, cursor)
            x[n] = left
            cursor = left + dims[n][0] + gap_x
        hmax = max(dims[n][1] for n in row)
        for n in row:
            y[n] = cy + (hmax - dims[n][1]) / 2
        cy += hmax + gap_y
    minx = min(x.values())
    for n in x:
        x[n] = x[n] - minx + 40
    return x, y, dims, back


STYLE = {
    'terminal': 'rounded=1;arcSize=50;whiteSpace=wrap;html=1;fillColor=#dbeafe;strokeColor=#3b82f6;fontStyle=1;',
    'process': 'rounded=1;arcSize=8;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#64748b;',
    'decision': 'rhombus;whiteSpace=wrap;html=1;fillColor=#fef3c7;strokeColor=#d97706;',
    'data': 'shape=cylinder3;whiteSpace=wrap;html=1;boundedLbl=1;backgroundOutline=1;size=7;fillColor=#dcfce7;strokeColor=#16a34a;',
    'link': 'shape=process;whiteSpace=wrap;html=1;backgroundOutline=1;size=0.06;fillColor=#ede9fe;strokeColor=#7c3aed;',
}
EDGE = 'edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;html=1;fontSize=11;labelBackgroundColor=#ffffff;endArrow=block;endFill=1;strokeColor=#334155;'
BACK = EDGE.replace('strokeColor=#334155;', 'strokeColor=#94a3b8;dashed=1;')


def attr(s):
    return escape(s, quote=True)


def diagram(idx, title, nodes, order, edges):
    x, y, dims, back = layout(nodes, order, edges)
    cells = ['<mxCell id="0"/>', '<mxCell id="1" parent="0"/>']
    p = f'p{idx}-'
    for n in order:
        w, h = dims[n]
        cells.append(
            f'<mxCell id="{p}{n}" value="{attr(nodes[n]["label"])}" style="{STYLE[nodes[n]["kind"]]}fontSize=12;" vertex="1" parent="1">'
            f'<mxGeometry x="{x[n]:.0f}" y="{y[n]:.0f}" width="{w}" height="{h}" as="geometry"/></mxCell>'
        )
    for k, (a, b, label) in enumerate(edges):
        style = BACK if (a, b) in back else EDGE
        cells.append(
            f'<mxCell id="{p}e{k}" value="{attr(label or "")}" style="{style}" edge="1" parent="1" source="{p}{a}" target="{p}{b}">'
            f'<mxGeometry relative="1" as="geometry"/></mxCell>'
        )
    width = int(max(x[n] + dims[n][0] for n in order) + 80)
    height = int(max(y[n] + dims[n][1] for n in order) + 80)
    return (
        f'<diagram id="flow-{idx}" name="{attr(title)}"><mxGraphModel dx="1200" dy="800" grid="1" gridSize="10" guides="1" '
        f'tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="{width}" pageHeight="{height}" math="0" shadow="0">'
        f'<root>{"".join(cells)}</root></mxGraphModel></diagram>'
    )


def legend():
    items = [
        ('terminal', 'Mulai / Selesai'),
        ('process', 'Langkah / tampilan yang dilakukan staf'),
        ('decision', 'Keputusan / percabangan'),
        ('data', 'Proses sistem / simpan ke database'),
        ('link', 'Lanjut ke alur lain (halaman lain di file ini)'),
    ]
    cells = ['<mxCell id="0"/>', '<mxCell id="1" parent="0"/>',
             '<mxCell id="lg-title" value="&lt;b&gt;Flowchart Sistem Informasi Aset SHD&lt;/b&gt;&lt;br&gt;Satu halaman per alur (tab di bawah). Dibuat dari docs/flowchart.md." '
             'style="text;html=1;align=left;verticalAlign=top;fontSize=14;" vertex="1" parent="1"><mxGeometry x="40" y="30" width="520" height="50" as="geometry"/></mxCell>']
    yy = 100
    for k, (kind, text) in enumerate(items):
        w, h = (130, 46) if kind == 'terminal' else ((150, 80) if kind == 'decision' else (150, 50))
        cells.append(f'<mxCell id="lg{k}" value="" style="{STYLE[kind]}" vertex="1" parent="1"><mxGeometry x="40" y="{yy}" width="{w}" height="{h}" as="geometry"/></mxCell>')
        cells.append(f'<mxCell id="lgt{k}" value="{attr(text)}" style="text;html=1;align=left;verticalAlign=middle;fontSize=12;" vertex="1" parent="1">'
                     f'<mxGeometry x="210" y="{yy}" width="340" height="{h}" as="geometry"/></mxCell>')
        yy += h + 20
    cells.append(f'<mxCell id="lge1" value="Alur maju" style="{EDGE}" edge="1" parent="1"><mxGeometry relative="1" as="geometry">'
                 f'<mxPoint x="40" y="{yy + 20}" as="sourcePoint"/><mxPoint x="190" y="{yy + 20}" as="targetPoint"/></mxGeometry></mxCell>')
    cells.append(f'<mxCell id="lge2" value="Kembali ke langkah sebelumnya" style="{BACK}" edge="1" parent="1"><mxGeometry relative="1" as="geometry">'
                 f'<mxPoint x="40" y="{yy + 70}" as="sourcePoint"/><mxPoint x="190" y="{yy + 70}" as="targetPoint"/></mxGeometry></mxCell>')
    return ('<diagram id="flow-0" name="0. Keterangan"><mxGraphModel dx="1200" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" '
            'arrows="1" fold="1" page="1" pageScale="1" pageWidth="827" pageHeight="1169" math="0" shadow="0">'
            f'<root>{"".join(cells)}</root></mxGraphModel></diagram>')


def read_md(path):
    """Ambil (judul, isi) tiap blok: '## judul' diikuti ```mermaid flowchart TD ...```."""
    text = Path(path).read_text(encoding='utf-8')
    flows = []
    for m in re.finditer(r'^## (.+?)\n+```mermaid\n(.*?)```', text, re.S | re.M):
        body = [l for l in m.group(2).splitlines() if l.strip() and not l.strip().startswith('flowchart')]
        flows.append((m.group(1).strip(), '\n'.join(body)))
    return flows


def main():
    docs = Path(__file__).resolve().parent.parent
    flows = read_md(docs / 'flowchart.md')
    diagrams = [legend()] + [diagram(i, t, *parse(s)) for i, (t, s) in enumerate(flows, start=1)]
    xml = '<mxfile host="drawio" agent="generated" version="24.0.0">' + ''.join(diagrams) + '</mxfile>\n'
    (docs / 'flowchart.drawio').write_text(xml, encoding='utf-8', newline='\n')
    print(f'docs/flowchart.drawio: {len(flows)} alur')


if __name__ == '__main__':
    main()
