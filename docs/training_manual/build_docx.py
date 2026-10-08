#!/usr/bin/env python3
"""Build TRAINING_MANUAL.docx from TRAINING_MANUAL.md with screenshots.

    pip install python-docx
    python3 docs/training_manual/build_docx.py

Reads ../../TRAINING_MANUAL.md, inserts the illustrations listed in FIGURES
under the matching headings, and writes ../../TRAINING_MANUAL.docx.
Re-run it whenever the markdown or the screenshots change.
"""
from __future__ import annotations

import datetime as dt
import re
from pathlib import Path

from docx import Document
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
SRC = ROOT / "TRAINING_MANUAL.md"
OUT = ROOT / "TRAINING_MANUAL.docx"
IMG = HERE / "images"

# Heading text prefix -> list of (image file, caption). Two portrait images in
# one tuple are placed side by side.
FIGURES: dict[str, list] = {
    "Scenario 1.1": [
        ("tm-01-login.png", "The sign-in screen."),
        ("tm-02-dashboard.png", "Manager and administrator home: the Dashboard with quick links and Launch POS."),
    ],
    "Scenario 1.3": [
        ("tm-03-nav-active.png", "Active page: Inventory is highlighted and Products & Menu has the yellow background."),
    ],
    "Scenario 1.4": [
        (("tm-30-phone-dashboard.png", "tm-31-phone-menu.png"),
         "The phone navbar closed (left) and with Menu open and Management expanded (right)."),
    ],
    "Scenario 1.5": [
        ("tm-04-workstation.png", "Select Workstation: the current station has the yellow border."),
    ],
    "Scenario 2.1": [
        ("tm-05-pos-start-shift.png", "Start New Shift with the opening float and REQUEST APPROVAL."),
        ("tm-05b-pos-pending.png", "Awaiting Manager Approval: the manager types their credentials here."),
    ],
    "Scenario 2.2": [
        ("tm-06-pos-grid.png", "The live POS: categories on the left, the Order panel with CHARGE, ADD TO TAB, CLEAR and LOST STOCK on the right."),
        ("tm-07-pos-payment.png", "The Payment window: tip, customer name, Single/Split pay, method, tendered and change."),
    ],
    "Scenario 2.13": [
        ("tm-07b-pos-end-shift.png", "End Shift: counted cash, variance reason and manager authorization."),
    ],
    "Scenario 4.1": [
        ("tm-20-menu-builder.png", "Menu Builder: dishes and their recipes."),
    ],
    "Scenario 4.2": [
        ("tm-19-production.png", "Meal Production: pick the dish, the destination station and the quantity."),
    ],
    "Scenario 4.4": [
        ("tm-18-kds.png", "Kitchen Display with nothing waiting: All Orders Clear."),
    ],
    "Scenario 5.1.1": [
        ("tm-17-users.png", "Users & Staff with Add New User."),
    ],
    "Scenario 5.1.3": [
        ("tm-24-shifts.png", "Shifts & X-Reads: pending and closed shifts."),
    ],
    "Scenario 5.2.1": [
        ("tm-08-products.png", "Product Catalog with filters for category, type, tax class, UNSPSC and status."),
        ("tm-08b-product-form.png", "Add Product: tax class and UNSPSC code sit next to each other."),
    ],
    "Scenario 5.2.2": [
        ("tm-26-categories.png", "Categories with their routing type and parent."),
    ],
    "Scenario 5.2.3": [
        ("tm-09-stock-levels.png", "Stock Levels: filter by location, enter physical counts."),
    ],
    "Scenario 5.2.4": [
        ("tm-25-audit.png", "Inventory Audit Trail filtered by action."),
    ],
    "Scenario 5.3.2": [
        ("tm-12-purchase-orders.png", "Purchasing: Purchase Orders tab."),
    ],
    "Scenario 5.3.4": [
        ("tm-11-receiving-bay.png", "Receiving Bay: V-SIAS consignments waiting to be booked."),
    ],
    "Scenario 5.4.1": [
        ("tm-10-transfers.png", "Stock Requisitions & Transfers: Incoming, Outgoing and My Pending Requests."),
    ],
    "Scenario 5.4.2": [
        ("tm-16-approvals.png", "The Approvals panel showing the V-SIAS sign-in, with Open in new tab at the top."),
    ],
    "Scenario 5.5.1": [
        ("tm-13-reports.png", "Master Reports: date range, store and employee filters, the five tiles and the Receipts tab."),
    ],
    "Scenario 5.5.6": [
        ("tm-32-phone-reports.png", "Master Reports on a phone: full-width filters, two-column tiles, scrolling tab pills."),
    ],
    "Scenario 5.5.7": [
        ("tm-14-zread.png", "End of Day (Z-Read)."),
    ],
    "5.6 Members": [
        ("tm-21-members.png", "Members with Register Member."),
    ],
    "Scenario 5.7.1": [
        ("tm-22-locations.png", "Locations: POS and Goods receiver flags per location."),
    ],
    "Scenario 5.7.2": [
        ("tm-23-tables.png", "Table Settings with Add New Table."),
    ],
    "Scenario 6.1": [
        ("tm-15-zra.png", "ZRA Smart Invoice after Test connection (902): tiles, device card and action buttons."),
    ],
    "8. Mobile and tablet": [
        ("tm-33-phone-pos.png", "The POS on a phone: the Start New Shift dialog before the station opens."),
    ],
}

DESKTOP_WIDTH = Cm(16.5)
PORTRAIT_WIDTH = Cm(7.6)
GREEN = RGBColor(0x19, 0x87, 0x54)
RED = RGBColor(0xB0, 0x2A, 0x37)
GREY = RGBColor(0x55, 0x5B, 0x61)
YELLOW = RGBColor(0xB5, 0x8A, 0x00)

INLINE = re.compile(r"(\*\*[^*]+\*\*|`[^`]+`|\[[^\]]+\]\([^)]+\)|\*[^*]+\*|→|‹|›)")


def add_runs(par, text: str, base_bold=False, base_italic=False, size=None, color=None):
    """Render markdown inline formatting (**bold**, *italic*, `code`, [t](url)) into runs."""
    pos = 0
    for m in INLINE.finditer(text):
        if m.start() > pos:
            _run(par, text[pos:m.start()], base_bold, base_italic, size, color)
        tok = m.group(0)
        if tok.startswith("**"):
            _run(par, tok[2:-2], True, base_italic, size, color)
        elif tok.startswith("`"):
            r = _run(par, tok[1:-1], base_bold, False, size, RGBColor(0x8B, 0x1A, 0x1A))
            r.font.name = "Consolas"
            r._element.rPr.rFonts.set(qn("w:eastAsia"), "Consolas")
        elif tok.startswith("["):
            label, url = re.match(r"\[([^\]]+)\]\(([^)]+)\)", tok).groups()
            _run(par, label, base_bold, base_italic, size, color)
            if not url.endswith(".md"):
                _run(par, f" ({url})", base_bold, True, size, GREY)
        elif tok.startswith("*"):
            _run(par, tok[1:-1], base_bold, True, size, color)
        else:
            _run(par, tok, base_bold, base_italic, size, color)
        pos = m.end()
    if pos < len(text):
        _run(par, text[pos:], base_bold, base_italic, size, color)


def _run(par, text, bold, italic, size, color):
    r = par.add_run(text)
    r.bold = bold or None
    r.italic = italic or None
    if size:
        r.font.size = size
    if color is not None:
        r.font.color.rgb = color
    return r


def shade(cell, hex_fill: str):
    tcPr = cell._element.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hex_fill)
    tcPr.append(shd)


def add_field(par, instr: str):
    """Insert a Word field (TOC, PAGE...)."""
    r = par.add_run()
    for tag, extra in (("begin", None), (None, instr), ("separate", None), (None, "Right-click → Update field"), ("end", None)):
        if tag:
            fc = OxmlElement("w:fldChar")
            fc.set(qn("w:fldCharType"), tag)
            r._element.append(fc)
        elif extra == instr:
            it = OxmlElement("w:instrText")
            it.set(qn("xml:space"), "preserve")
            it.text = instr
            r._element.append(it)
        else:
            t = OxmlElement("w:t")
            t.text = extra
            r._element.append(t)


class Builder:
    def __init__(self):
        self.doc = Document()
        self.fig_no = 0
        self._setup_page()
        self._setup_styles()

    # ---- document set-up -------------------------------------------------
    def _setup_page(self):
        s = self.doc.sections[0]
        s.orientation = WD_ORIENT.PORTRAIT
        s.page_width, s.page_height = Cm(21.0), Cm(29.7)
        for side in ("left_margin", "right_margin"):
            setattr(s, side, Cm(2.0))
        s.top_margin = Cm(2.0)
        s.bottom_margin = Cm(1.8)
        footer = s.footer.paragraphs[0]
        footer.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = footer.add_run("smartTrade POS Pro — Training manual · page ")
        r.font.size = Pt(8)
        r.font.color.rgb = GREY
        add_field(footer, "PAGE")

    def _setup_styles(self):
        st = self.doc.styles
        normal = st["Normal"]
        normal.font.name = "Calibri"
        normal.font.size = Pt(10.5)
        normal.paragraph_format.space_after = Pt(4)
        normal.paragraph_format.line_spacing = 1.08
        for name, size, color in (("Heading 1", 20, RGBColor(0x1C, 0x21, 0x28)),
                                  ("Heading 2", 14, RGBColor(0x1C, 0x21, 0x28)),
                                  ("Heading 3", 12, RGBColor(0x33, 0x33, 0x33))):
            h = st[name]
            h.font.name = "Calibri"
            h.font.size = Pt(size)
            h.font.bold = True
            h.font.color.rgb = color
            h.paragraph_format.space_before = Pt(14 if name == "Heading 1" else 10)
            h.paragraph_format.space_after = Pt(4)
            h.paragraph_format.keep_with_next = True

    # ---- blocks ----------------------------------------------------------
    def title_page(self, title: str, intro: list[str]):
        d = self.doc
        for _ in range(6):
            d.add_paragraph()
        p = d.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = p.add_run("smartTrade POS Pro")
        r.font.size, r.bold, r.font.color.rgb = Pt(30), True, RGBColor(0x1C, 0x21, 0x28)
        p = d.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = p.add_run("Training manual")
        r.font.size, r.font.color.rgb = Pt(22), YELLOW
        p = d.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = p.add_run("Scenario-based training and assessment for every role")
        r.font.size, r.italic, r.font.color.rgb = Pt(12), True, GREY
        d.add_paragraph()
        p = d.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = p.add_run(f"Odelia Enterprise · {dt.date.today():%B %Y}")
        r.font.size, r.font.color.rgb = Pt(11), GREY
        for _ in range(4):
            d.add_paragraph()
        for line in intro:
            p = d.add_paragraph()
            add_runs(p, line)
        d.add_page_break()
        h = d.add_paragraph("Contents", style="Heading 1")
        p = d.add_paragraph()
        add_field(p, 'TOC \\o "1-3" \\h \\z \\u')
        p = d.add_paragraph()
        r = p.add_run("If the contents list is empty, right-click it and choose Update Field (Word), or use Tools → Update All (LibreOffice).")
        r.italic, r.font.size, r.font.color.rgb = True, Pt(9), GREY
        d.add_page_break()

    def heading(self, level: int, text: str):
        if level == 1:
            # start every chapter on a new page
            last = self.doc.paragraphs[-1] if self.doc.paragraphs else None
            if last is not None and last.text.strip():
                self.doc.add_page_break()
        p = self.doc.add_paragraph(style=f"Heading {min(level, 3)}")
        add_runs(p, text, base_bold=True)
        for key, figs in FIGURES.items():
            if text == key or text.startswith(f"{key} "):
                for item in figs:
                    self.figure(*item)
                break

    def figure(self, files, caption: str):
        self.fig_no += 1
        if isinstance(files, tuple):
            t = self.doc.add_table(rows=1, cols=len(files))
            t.alignment = WD_TABLE_ALIGNMENT.CENTER
            for cell, f in zip(t.rows[0].cells, files):
                cell.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
                cell.paragraphs[0].add_run().add_picture(str(IMG / f), width=PORTRAIT_WIDTH)
        else:
            p = self.doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p.paragraph_format.keep_with_next = True
            path = IMG / files
            width = PORTRAIT_WIDTH if "phone" in files else DESKTOP_WIDTH
            p.add_run().add_picture(str(path), width=width)
        cap = self.doc.add_paragraph()
        cap.alignment = WD_ALIGN_PARAGRAPH.CENTER
        cap.paragraph_format.space_after = Pt(10)
        r = cap.add_run(f"Figure {self.fig_no} — {caption}")
        r.italic, r.font.size, r.font.color.rgb = True, Pt(9), GREY

    def paragraph(self, text: str):
        p = self.doc.add_paragraph()
        low = text.lower()
        if low.startswith("*expected result:*"):
            p.paragraph_format.left_indent = Cm(0.6)
            r = p.add_run("Expected result: ")
            r.bold, r.font.color.rgb = True, GREEN
            add_runs(p, text[len("*expected result:*"):].strip())
        elif low.startswith("**if it goes wrong:**"):
            p.paragraph_format.left_indent = Cm(0.6)
            r = p.add_run("If it goes wrong: ")
            r.bold, r.font.color.rgb = True, RED
            add_runs(p, text[len("**if it goes wrong:**"):].strip())
        elif low.startswith("situation:"):
            r = p.add_run("Situation: ")
            r.bold, r.font.color.rgb = True, GREY
            add_runs(p, text[len("situation:"):].strip(), base_italic=True)
        else:
            add_runs(p, text)

    def bullet(self, text: str, checkbox=False):
        p = self.doc.add_paragraph(style="List Bullet")
        if checkbox:
            p.add_run("☐  ")
        add_runs(p, text)

    def numbered(self, number: str, text: str):
        p = self.doc.add_paragraph()
        p.paragraph_format.left_indent = Cm(0.9)
        p.paragraph_format.first_line_indent = Cm(-0.6)
        r = p.add_run(f"{number}.  ")
        r.bold = True
        add_runs(p, text)

    def table(self, rows: list[list[str]]):
        header, body = rows[0], rows[1:]
        t = self.doc.add_table(rows=1, cols=len(header))
        t.style = "Table Grid"
        t.alignment = WD_TABLE_ALIGNMENT.CENTER
        for i, h in enumerate(header):
            c = t.rows[0].cells[i]
            c.paragraphs[0].text = ""
            add_runs(c.paragraphs[0], h, base_bold=True, size=Pt(9.5))
            shade(c, "1C2128")
            for r in c.paragraphs[0].runs:
                r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        for ri, row in enumerate(body):
            cells = t.add_row().cells
            for i in range(len(header)):
                val = row[i] if i < len(row) else ""
                cells[i].paragraphs[0].text = ""
                add_runs(cells[i].paragraphs[0], val, size=Pt(9.5))
                if ri % 2 == 1:
                    shade(cells[i], "F4F6F9")
        self.doc.add_paragraph().paragraph_format.space_after = Pt(2)

    def save(self):
        self.doc.save(OUT)


def parse_table_row(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def build():
    lines = SRC.read_text(encoding="utf-8").splitlines()
    b = Builder()

    # Title + intro (everything before the first '---')
    title = lines[0].lstrip("# ").strip()
    i = 1
    intro = []
    while i < len(lines) and lines[i].strip() != "---":
        if lines[i].strip():
            intro.append(lines[i].strip())
        i += 1
    b.title_page(title, intro)

    table_buf: list[list[str]] = []

    def flush_table():
        nonlocal table_buf
        if table_buf:
            rows = [r for r in table_buf if not all(re.fullmatch(r":?-{2,}:?", c) for c in r)]
            b.table(rows)
            table_buf = []

    while i < len(lines):
        raw = lines[i]
        line = raw.rstrip()
        i += 1
        if line.startswith("|"):
            table_buf.append(parse_table_row(line))
            continue
        flush_table()
        if not line.strip() or line.strip() == "---":
            continue
        m = re.match(r"^(#{1,4})\s+(.*)", line)
        if m:
            b.heading(len(m.group(1)), m.group(2).strip())
            continue
        m = re.match(r"^- \[[ xX]\]\s+(.*)", line)
        if m:
            b.bullet(m.group(1), checkbox=True)
            continue
        m = re.match(r"^[-*]\s+(.*)", line)
        if m:
            b.bullet(m.group(1))
            continue
        m = re.match(r"^(\d+)\.\s+(.*)", line)
        if m:
            b.numbered(m.group(1), m.group(2))
            continue
        b.paragraph(line.strip())
    flush_table()
    b.save()
    print(f"wrote {OUT} ({OUT.stat().st_size // 1024} KB, {b.fig_no} figures)")


if __name__ == "__main__":
    build()
