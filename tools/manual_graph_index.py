"""Build a deterministic Graphify navigation index without an LLM."""

from pathlib import Path
import json
import re


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "graphify-out"
IGNORED = {".git", "graphify-out"}


def slug(value: str) -> str:
    return re.sub(r"[^a-z0-9_]+", "_", value.lower()).strip("_") or "root"


def main() -> None:
    OUT.mkdir(exist_ok=True)
    files = [
        path for path in ROOT.rglob("*")
        if path.is_file() and not any(part in IGNORED for part in path.relative_to(ROOT).parts)
    ]
    nodes, edges, ids = [], [], {}

    def add_node(kind: str, rel: str, label: str) -> None:
        node_id = f"{kind}_{slug(rel)}"
        ids[rel] = node_id
        nodes.append({"id": node_id, "label": label, "file_type": kind,
                      "source_file": rel, "source_location": None})

    add_node("concept", ".", ROOT.name)
    directories = sorted({path.parent for path in files}, key=lambda p: (len(p.parts), str(p).lower()))
    for directory in directories:
        rel = directory.relative_to(ROOT).as_posix()
        parent = directory.parent.relative_to(ROOT).as_posix() if directory != ROOT else "."
        add_node("concept", rel, directory.name)
        if parent in ids:
            edges.append({"source": ids[parent], "target": ids[rel], "relation": "contains",
                          "confidence": "EXTRACTED", "confidence_score": 1.0, "source_file": rel})

    for path in sorted(files, key=lambda p: p.relative_to(ROOT).as_posix().lower()):
        rel = path.relative_to(ROOT).as_posix()
        suffix = path.suffix.lower()
        kind = "document" if suffix in {".md", ".txt", ".rst"} else "paper" if suffix == ".pdf" else "image" if suffix in {".png", ".jpg", ".jpeg", ".webp", ".gif"} else "concept"
        add_node(kind, rel, path.name)
        parent = path.parent.relative_to(ROOT).as_posix()
        edges.append({"source": ids[parent], "target": ids[rel], "relation": "contains",
                      "confidence": "EXTRACTED", "confidence_score": 1.0, "source_file": rel})

    for path in files:
        if path.suffix.lower() != ".md":
            continue
        rel = path.relative_to(ROOT).as_posix()
        for target in re.findall(r"\[[^\]]*\]\(([^)#]+)", path.read_text(encoding="utf-8", errors="ignore")):
            target_path = (path.parent / target.strip()).resolve()
            try:
                target_rel = target_path.relative_to(ROOT).as_posix()
            except ValueError:
                continue
            if target_rel in ids:
                edges.append({"source": ids[rel], "target": ids[target_rel], "relation": "references",
                              "confidence": "EXTRACTED", "confidence_score": 1.0, "source_file": rel})

    graph = {"directed": True, "nodes": nodes, "edges": edges, "hyperedges": [],
             "input_tokens": 0, "output_tokens": 0, "index_mode": "manual-structural"}
    (OUT / "graph.json").write_text(json.dumps(graph, ensure_ascii=False, indent=2), encoding="utf-8")
    (OUT / "GRAPH_REPORT.md").write_text(
        f"# BOVITRACK Graphify index\n\nÍndice estructural sin análisis semántico ni modelo.\n\n"
        f"- Nodos: {len(nodes)}\n- Relaciones explícitas: {len(edges)}\n",
        encoding="utf-8",
    )
    print(f"Indexed {len(nodes)} nodes and {len(edges)} edges")


if __name__ == "__main__":
    main()
