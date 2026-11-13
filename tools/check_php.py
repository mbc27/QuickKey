# -*- coding: utf-8 -*-
"""简易PHP结构校验：剔除字符串/注释后统计括号配对"""
import io
import re
import sys

def strip_code(s: str) -> str:
    out = []
    i, n = 0, len(s)
    instr = None
    comment = None
    while i < n:
        c = s[i]
        two = s[i:i+2]
        if comment:
            if comment == '//' and c == '\n':
                comment = None
                out.append(c)
            elif comment == '/*' and two == '*/':
                comment = None
                i += 2
                continue
            i += 1
            continue
        if instr:
            if c == '\\':
                i += 2
                continue
            if c == instr:
                instr = None
            i += 1
            continue
        if two in ('//', '/*'):
            comment = two
            i += 2
            continue
        if c in ('"', "'"):
            instr = c
            i += 1
            continue
        out.append(c)
        i += 1
    return ''.join(out)

def check(path):
    s = io.open(path, encoding='utf-8').read()
    code = strip_code(s)
    res = {k: code.count(k[0]) - code.count(k[1]) for k in ('{}', '()', '[]')}
    print(path.split('\\')[-1], res)
    return all(v == 0 for v in res.values())

if __name__ == '__main__':
    ok = True
    for f in sys.argv[1:]:
        ok = check(f) and ok
    print('ALL BALANCED' if ok else 'IMBALANCE FOUND')
