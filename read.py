import zipfile
import xml.etree.ElementTree as ET

def get_docx_text(path):
    z = zipfile.ZipFile(path)
    xml_content = z.read('word/document.xml')
    root = ET.fromstring(xml_content)
    ns = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
    paras = root.findall('.//w:p', ns)
    texts = []
    for p in paras:
        parts = p.findall('.//w:t', ns)
        if parts:
            text = ''.join(node.text for node in parts if node.text)
            texts.append(text)
    return '\n'.join(texts)

text = get_docx_text(r'C:\xampp\htdocs\farmacia_misalud\farmacia2026\GUIA DE INICIO MODULOS DE LOGIN Y PERMISOS.docx')
with open(r'c:\xampp\htdocs\farmacia_misalud\farmacia2026\guia.txt', 'w', encoding='utf-8') as f:
    f.write(text)
