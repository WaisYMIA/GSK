import re, sys, glob, os
GLOBAL_OK=set("""self static parent true false null array string int float bool void mixed callable iterable object never
Exception RuntimeException InvalidArgumentException LogicException Throwable Closure
""".split())
def strip(code):
    out=[];i=0;n=len(code)
    while i<n:
        c=code[i]; two=code[i:i+2]
        if two=='//' or c=='#' and code[i:i+2]!='#[':
            j=code.find('\n',i); i=n if j<0 else j; continue
        if two=='/*':
            j=code.find('*/',i+2); i=n if j<0 else j+2; continue
        if c in '\'"':
            q=c;i+=1
            while i<n and code[i]!=q:
                i+=2 if code[i]=='\\' else 1
            i+=1; out.append('""'); continue
        out.append(c); i+=1
    return ''.join(out)
def known_classes():
    k={}
    for p in glob.glob('app/**/*.php',recursive=True)+glob.glob('tests/**/*.php',recursive=True)+glob.glob('database/**/*.php',recursive=True):
        t=open(p).read(); ns=re.search(r'namespace\s+([\w\\]+);',t)
        for m in re.finditer(r'^(?:final\s+|abstract\s+)?(?:class|trait|interface|enum)\s+(\w+)',t,re.M):
            k[(ns.group(1) if ns else '')+'\\'+m.group(1)]=p
    return k
KNOWN=known_classes()
problems=[]; files=[p for p in glob.glob('**/*.php',recursive=True) if not p.startswith('vendor')]
for p in sorted(files):
    raw=open(p).read()
    if not raw.startswith('<?php'): problems.append((p,'does not start with <?php')); continue
    code=strip(raw)
    for a,b in ['()','{}','[]']:
        if code.count(a)!=code.count(b): problems.append((p,f'unbalanced {a}{b}: {code.count(a)} vs {code.count(b)}'))
    ns=re.search(r'namespace\s+([\w\\]+);',raw); ns=ns.group(1) if ns else ''
    imports={}
    for m in re.finditer(r'^use\s+([\w\\]+)(?:\s+as\s+(\w+))?;',raw,re.M):
        imports[m.group(2) or m.group(1).split('\\')[-1]]=m.group(1)
    for m in re.finditer(r'^use\s+([\w\\]+)\\\{([^}]*)\}',raw,re.M): pass
    # trait uses inside classes
    body=re.sub(r'^use\s+[^;]+;','',code,flags=re.M)
    used=set()
    for m in re.finditer(r'(?<![\w\\$>:])new\s+([A-Z]\w*)',body): used.add(m.group(1))
    for m in re.finditer(r'(?<![\w\\$>])([A-Z]\w*)::',body): used.add(m.group(1))
    for m in re.finditer(r'instanceof\s+([A-Z]\w*)',body): used.add(m.group(1))
    for m in re.finditer(r'(?:\(|,)\s*\??([A-Z]\w*)\s+\$',body): used.add(m.group(1))
    for m in re.finditer(r'\):\s*\??([A-Z]\w*)\b',body): used.add(m.group(1))
    for m in re.finditer(r'catch\s*\(\s*([A-Z]\w*)',body): used.add(m.group(1))
    for m in re.finditer(r'#\[(\w+)',raw): used.add(m.group(1))
    for m in re.finditer(r'\buse\s+((?:[A-Z]\w*)(?:\s*,\s*[A-Z]\w*)*)\s*;',strip(raw)):
        pass
    for m in re.finditer(r'(?:extends|implements)\s+([A-Z]\w*)',body): used.add(m.group(1))
    for name in used:
        if name in GLOBAL_OK or name in imports: continue
        if (ns+'\\'+name) in KNOWN or ('\\'+name) in KNOWN: continue
        # classes declared in this same file
        if re.search(r'class\s+'+name+r'\b',raw): continue
        problems.append((p,f'class "{name}" used but not imported or defined'))
    # imports that do not resolve to a known project class when under App/Tests/Database
    for alias,full in imports.items():
        if full.startswith(('App\\','Tests\\','Database\\Seeders','Database\\Factories')) and full not in KNOWN:
            problems.append((p,f'import {full} does not exist in the project'))
        if not re.search(r'\b'+alias+r'\b',body) and not re.search(r'#\['+alias,raw):
            problems.append((p,f'unused import {alias}'))
print(len(files),'PHP files checked')
for p,m in problems: print('  ',p,'-',m)
print('problems:',len(problems))
