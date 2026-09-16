"""
Rebuild every deliverable from docs/nexhris-appendices.html.

    python docs/rebuild.py

Edit the diagrams in nexhris-appendices.html, run this, and you get fresh:
    docs/diagrams/*.svg   editable vector, for Word / draw.io / Inkscape
    docs/images/*.png     ~390 DPI raster, for pasting anywhere
    docs/NexHRIS-Appendices.pdf   the 7-page manuscript appendix

Requires Microsoft Edge (for rendering) and XAMPP Apache running on :80.
"""
import os
import re
import shutil
import subprocess
import sys
import xml.etree.ElementTree as ET

ROOT = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(ROOT, "nexhris-appendices.html")
URL_BASE = "http://localhost/nexhris/docs"

FILES = [
    ("appendix-C-flow-chart-manual-process",   "Flow Chart of Manual Process"),
    ("appendix-D-use-case-diagram",            "Use Case Diagram"),
    ("appendix-E-conceptual-diagram-level-0",  "Conceptual Diagram (Level 0)"),
    ("appendix-F-dataflow-diagram-level-1",    "Dataflow Diagram Level 1"),
    ("appendix-G-entity-relationship-diagram", "Entity Relationship Diagram"),
    ("appendix-H-flow-chart-developed-system", "Flow Chart of Developed System"),
    ("appendix-I-ipo-chart",                   "Input-Process-Output Chart"),
]

# font sizes carried by the .t* / label classes in the page stylesheet
FS = {"t12": 12, "t11": 11, "t10": 10, "t9": 9, "t8": 8, "t7": 7, "t6": 6,
      "lb": 9, "lb8": 8, "lb7": 7, "yn": 8.5}
# labels that sit on top of connector lines and need a white backing rect
HALO = {"lb", "lb8", "lb7", "yn"}


def find_edge():
    for var in ("ProgramFiles(x86)", "ProgramFiles", "LOCALAPPDATA"):
        base = os.environ.get(var)
        if not base:
            continue
        p = os.path.join(base, "Microsoft", "Edge", "Application", "msedge.exe")
        if os.path.exists(p):
            return p
    return shutil.which("msedge") or shutil.which("chrome")


def attrs_of(s):
    return dict(re.findall(r'([a-zA-Z-]+)\s*=\s*"([^"]*)"', s))


def emit(tag, a, inner=None):
    s = "<" + tag + "".join(f' {k}="{v}"' for k, v in a.items())
    return s + ("/>" if inner is None else ">" + inner + f"</{tag}>")


def resolve(tag, raw):
    """Replace class-based styling with presentation attributes.

    Explicit attributes already on the element always win, so a per-element
    stroke-width override in the HTML survives the conversion.
    """
    a = attrs_of(raw)
    classes = set((a.pop("class", "") or "").split())
    d = {}
    if tag == "text":
        d["font-family"] = "Arial, Helvetica, sans-serif"
        d["fill"] = "#000"
        for k in classes:
            if k in FS:
                d["font-size"] = str(FS[k])
        if "b" in classes or "yn" in classes:
            d["font-weight"] = "bold"
    else:
        if "n" in classes:
            d.update({"fill": "#fff", "stroke": "#000", "stroke-width": "1.1"})
        if "n2" in classes:
            d.update({"fill": "#fff", "stroke": "#000", "stroke-width": "1.4"})
        if "ln" in classes:
            d.update({"fill": "none", "stroke": "#000", "stroke-width": "1"})
        if "lnb" in classes:
            d.update({"fill": "none", "stroke": "#000", "stroke-width": "1.3"})
    for k, v in d.items():
        a.setdefault(k, v)
    return a, classes


def export_svgs(src):
    os.makedirs(os.path.join(ROOT, "diagrams"), exist_ok=True)
    found = re.findall(r'(<svg viewBox="0 0 (\d+) (\d+)".*?</svg>)', src, re.S)
    if len(found) != len(FILES):
        sys.exit(f"expected {len(FILES)} diagrams in the page, found {len(found)}")

    for (whole, W, H), (name, title) in zip(found, FILES):
        W, H = int(W), int(H)
        body = re.sub(r"^<svg[^>]*>", "", whole).replace("</svg>", "")
        body = re.sub(r"<!--.*?-->", "", body, flags=re.S)
        halos = []

        def do_text(m):
            a, classes = resolve("text", m.group(1))
            inner = m.group(2)
            el = emit("text", a, inner)
            if classes & HALO:
                # Word's SVG renderer ignores paint-order, so the CSS halo is
                # rebuilt here as a real rect and lifted into a top layer.
                fs = float(a.get("font-size", 9))
                txt = re.sub(r"&[a-z]+;|&#\d+;", "x", re.sub(r"<[^>]+>", "", inner))
                w = len(txt) * fs * (0.58 if a.get("font-weight") == "bold" else 0.55)
                x, y = float(a["x"]), float(a["y"])
                anchor = a.get("text-anchor", "start")
                x0 = x if anchor == "start" else (x - w / 2 if anchor == "middle" else x - w)
                halos.append(
                    f'<rect x="{x0 - 2:.1f}" y="{y - fs * 0.82:.1f}" '
                    f'width="{w + 4:.1f}" height="{fs * 1.08:.1f}" fill="#fff"/>{el}')
                return ""
            return el

        # order matters: leaf text, then leaf shapes, then group open tags,
        # so a <g> never swallows children that still need converting
        body = re.sub(r"<text\b([^>]*)>(.*?)</text>", do_text, body, flags=re.S)
        body = re.sub(r"<(rect|circle|ellipse|path)\b([^>]*?)/>",
                      lambda m: emit(m.group(1), resolve(m.group(1), m.group(2))[0]), body)
        body = re.sub(r"<g\b([^>]*?)>",
                      lambda m: emit("g", resolve("g", m.group(1))[0], "")[:-4], body)

        out = (f'<?xml version="1.0" encoding="UTF-8"?>\n'
               f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" '
               f'width="{W}" height="{H}">\n'
               f"<title>{title} &#8212; NexHRIS, ISPSC Tagudin Campus</title>\n"
               f'<rect x="0" y="0" width="{W}" height="{H}" fill="#ffffff"/>\n'
               + body + "\n" + "\n".join(halos) + "\n</svg>\n")

        path = os.path.join(ROOT, "diagrams", name + ".svg")
        open(path, "w", encoding="utf-8").write(out)
        ET.parse(path)                       # fails loudly on malformed output
        print(f"  svg  {name}.svg  {W}x{H}")
        yield name, W, H


def render(edge, sizes):
    os.makedirs(os.path.join(ROOT, "images"), exist_ok=True)
    for name, W, H in sizes:
        wrapper = os.path.join(ROOT, f"_w_{name}.html")
        open(wrapper, "w", encoding="utf-8").write(
            "<style>html,body{margin:0;padding:0;background:#fff;overflow:hidden}"
            f"img{{display:block;width:{W}px;height:{H}px}}</style>\n"
            f'<img src="diagrams/{name}.svg">\n')
        png = os.path.join(ROOT, "images", name + ".png")
        subprocess.run([edge, "--headless=old", "--disable-gpu", "--hide-scrollbars",
                        "--force-device-scale-factor=4", f"--window-size={W},{H}",
                        "--virtual-time-budget=8000", f"--screenshot={png}",
                        f"{URL_BASE}/_w_{name}.html"],
                       check=False, capture_output=True)
        os.remove(wrapper)
        print(f"  png  {name}.png  {W * 4}x{H * 4}")

    pdf = os.path.join(ROOT, "NexHRIS-Appendices.pdf")
    if os.path.exists(pdf):
        os.remove(pdf)
    subprocess.run([edge, "--headless=old", "--disable-gpu",
                    "--run-all-compositor-stages-before-draw",
                    "--virtual-time-budget=20000", "--no-pdf-header-footer",
                    f"--print-to-pdf={pdf}", f"{URL_BASE}/nexhris-appendices.html"],
                   check=False, capture_output=True)
    if os.path.exists(pdf):
        pages = len(re.findall(rb"/Type\s*/Page[^s]", open(pdf, "rb").read()))
        print(f"  pdf  NexHRIS-Appendices.pdf  {pages} pages")
    else:
        print("  pdf  FAILED - is Apache running on :80?")


if __name__ == "__main__":
    edge = find_edge()
    if not edge:
        sys.exit("Microsoft Edge not found - needed to render PNG and PDF.")
    sizes = list(export_svgs(open(SRC, encoding="utf-8").read()))
    render(edge, sizes)
    print("done")
