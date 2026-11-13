# -*- coding: utf-8 -*-
"""
从 软件清单.md 生成 待补充清单.json：
- 提取全部软件名（排除 清单.json 已收录的软件及别名）
- 未能匹配到已知名称差异的进 SKIP 集合
- 为可确认官方文档/官网的软件补充 doc_url，其余留空（前端展示搜索入口）
重新运行即可在软件清单更新后重建数据。
"""
import io
import json
import re
import datetime

MD_FILE = r'd:\Trae-AI\Project\A_QuickKey\软件清单.md'
DATA_FILE = r'd:\Trae-AI\Project\A_QuickKey\清单.json'
OUT_FILE = r'd:\Trae-AI\Project\A_QuickKey\待补充清单.json'

# 与清单.json重复但名称写法不同的软件，跳过收录
SKIP = {'hbuilder', '云盘', 'vlcmediaplayer', 'obs', 'wpsoffice', 'microsoftoffice'}

# 官方文档 / 官网链接（能确认的才写）
DOCS = {
    'Microsoft Outlook': 'https://support.microsoft.com/zh-cn/office',
    'OneNote': 'https://support.microsoft.com/zh-cn/onenote',
    'LibreOffice Writer': 'https://help.libreoffice.org',
    'LibreOffice Calc': 'https://help.libreoffice.org',
    'LibreOffice Impress': 'https://help.libreoffice.org',
    'LibreOffice Base': 'https://help.libreoffice.org',
    'LibreOffice Math': 'https://help.libreoffice.org',
    'Adobe Acrobat DC': 'https://helpx.adobe.com/cn/acrobat/using/keyboard-shortcuts.html',
    'SumatraPDF': 'https://www.sumatrapdfreader.org/docs/Keyboard-shortcuts',
    'PDF-XChange Editor': 'https://www.tracker-software.com',
    'Nitro PDF': 'https://www.gonitro.com/',
    'ABBYY FineReader': 'https://www.abbyy.cn/',
    'NAPS2': 'https://www.naps2.com/',
    'Calibre': 'https://manual.calibre-ebook.com',
    'Sigil': 'https://github.com/Sigil-Ebook/Sigil',
    'Notion 桌面端': 'https://www.notion.so/help/keyboard-shortcuts',
    'Logseq': 'https://docs.logseq.com',
    'SiYuan 思源笔记': 'https://b3log.org/siyuan/',
    'Joplin': 'https://joplinapp.org/help/',
    '印象笔记': 'https://www.yinxiang.com/',
    '语雀客户端': 'https://www.yuque.com/',
    'XMind ZEN': 'https://xmind.cn/',
    '亿图图示 EdrawMax': 'https://www.edrawsoft.cn/',
    'EdrawMind': 'https://www.edrawsoft.cn/',
    'Evernote 国际版': 'https://evernote.com/',
    'Vivaldi': 'https://help.vivaldi.com/',
    'Opera': 'https://help.opera.com/',
    'Brave': 'https://support.brave.com/',
    'Sublime': 'https://www.sublimetext.com/docs/keymaps.html',
    'WPS PDF': 'https://help.wps.cn/',
    'WPS思维导图': 'https://help.wps.cn/',
    'WPS流程图': 'https://help.wps.cn/',
    '腾讯会议': 'https://meeting.tencent.com/',
    '企业微信': 'https://work.weixin.qq.com/',
    '飞书': 'https://www.feishu.cn/',
    'Adobe Lightroom Classic': 'https://helpx.adobe.com/cn/lightroom-classic/help/keyboard-shortcuts.html',
    'Adobe XD': 'https://helpx.adobe.com/cn/xd/',
    'Rhinoceros 3D': 'https://docs.mcneel.com/rhino/8/help/zh-cn/index.htm',
    'SOLIDWORKS': 'https://help.solidworks.com/',
    'ZBrush': 'https://docs.pixologic.com/',
    'Revit': 'https://help.autodesk.com/view/RVT/2025/CHS/',
    'Unity': 'https://docs.unity3d.com/cn/current/Manual/Shortcuts.html',
    'Unreal Engine': 'https://docs.unrealengine.com/',
    'Godot': 'https://docs.godotengine.org/zh-cn/stable/',
    'Git': 'https://git-scm.com/book/zh/v2',
    'Tabby': 'https://tabby.sh/',
    'WindTerm': 'https://github.com/kingToolbox/WindTerm',
    'EndNote': 'https://support.clarivate.com/',
    'NoteExpress': 'https://www.inoteexpress.cn/',
    'uTools': 'https://u.tools/',
    'Quicker': 'https://getquicker.net/',
    'Ditto': 'https://ditto-cp.sourceforge.io/',
}


def norm(s: str) -> str:
    """名称归一化：小写并去掉空格与符号，用于查重"""
    return re.sub(r'[^0-9a-z\u4e00-\u9fff]', '', s.lower())


def clean_name(raw: str):
    """去掉编号、括号备注；括号内含 Mac/跨平台 时识别平台"""
    name = raw.strip()
    name = re.sub(r'^\d+[\.\、]\s*', '', name)
    platforms = []
    m = re.search(r'[（(]([^）)]*)[）)]', name)
    if m:
        note = m.group(1)
        if 'mac' in note.lower() and 'win' not in note.lower():
            platforms = ['Mac']
        if '跨平台' in note or '全平台' in note:
            platforms = ['Windows', 'Mac', 'Linux']
        name = name[:m.start()].strip()
    name = name.strip('。 ，,、')
    return name, platforms


def main():
    data = json.load(io.open(DATA_FILE, encoding='utf-8'))
    known = set()
    for e in data['app_hotkeys']:
        known.add(norm(e['app_name']))
        for al in e.get('aliases', []) or []:
            known.add(norm(str(al)))

    text = io.open(MD_FILE, encoding='utf-8').read()
    lines = text.splitlines()

    pending = []
    seen = set()
    category = '热门软件（待优先补充）'

    for line in lines:
        s = line.strip()
        if not s:
            continue
        if s.startswith('## '):
            category = re.sub(r'^[\W_]+', '', s[3:]).strip() or s[3:].strip()
            continue
        # 顶层第一行的顿号分隔列表
        if '、' in s and not re.match(r'^\d+[\.\、]', s) and not s.startswith('#') and not s.startswith('>'):
            for part in s.split('、'):
                part = part.strip()
                if part:
                    pending.append((part, category))
            continue
        m = re.match(r'^\d+[\.\、]\s*(.+)$', s)
        if m:
            pending.append((m.group(1), category))

    out = []
    for raw, cat in pending:
        name, platforms = clean_name(raw)
        if not name or len(name) > 60:
            continue
        key = norm(name)
        if not key or key in known or key in SKIP or key in seen:
            continue
        seen.add(key)
        out.append({
            'name': name,
            'category': cat,
            'platforms': platforms,
            'doc_url': DOCS.get(name, ''),
            'updated': datetime.date.today().isoformat(),
        })

    out.sort(key=lambda x: (x['category'], x['name']))
    json.dump({'updated': datetime.date.today().isoformat(),
               'software': out},
              io.open(OUT_FILE, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('pending total:', len(out))
    with_docs = sum(1 for x in out if x['doc_url'])
    print('with doc_url:', with_docs)
    cats = {}
    for x in out:
        cats[x['category']] = cats.get(x['category'], 0) + 1
    for c, n in cats.items():
        print(f'  {c}: {n}')


if __name__ == '__main__':
    main()
