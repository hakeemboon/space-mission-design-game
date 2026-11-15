import math, itertools, json
MU=398600.4418; RE=6378.137
def orbit(h):
    a=RE+h; T=2*math.pi*math.sqrt(a**3/MU); ecl=math.asin(RE/a)/math.pi
    return T/60, 86400/T, ecl
for h in (450,500,600,705): 
    T,n,e=orbit(h); print(h, round(T,1), round(n,2), round(e,3), 'ecl min',round(e*T,1),'sun h/day',round((1-e)*24,1))
# catalogue
BUS={'S':dict(m=14,cap=8,slots=1,peak=60,hk=18,sto=16000,cost=150,life=24,dev=18),
     'M':dict(m=55,cap=25,slots=2,peak=160,hk=40,sto=64000,cost=280,life=36,dev=24),
     'L':dict(m=120,cap=45,slots=3,peak=320,hk=85,sto=128000,cost=430,life=60,dev=32)}
INS={'WFC':dict(m=4,p=12,r=2,cost=60,dev=1,s=dict(LAND=.4,COAST=.3,CLOUD=.8)),
     'SWIR':dict(m=7,p=25,r=4,cost=100,dev=2,s=dict(LAND=.7,COAST=.4,CLOUD=.6)),
     'MSI':dict(m=12,p=35,r=8,cost=140,dev=3,s=dict(LAND=1.0,COAST=.7,CLOUD=.5)),
     'TIR':dict(m=9,p=30,r=1.5,cost=120,dev=3,s=dict(LAND=.5,COAST=.5,CLOUD=1.0)),
     'HSI':dict(m=18,p=55,r=20,cost=220,dev=5,s=dict(LAND=.8,COAST=1.0,CLOUD=.2))}
PWR={'P1':dict(m=4,gen=50,bat=40,cost=50),'P2':dict(m=10,gen=120,bat=110,cost=110),'P3':dict(m=19,gen=260,bat=260,cost=190)}
COM={'C1':dict(m=1.5,p=10,r=2,band='S',cost=40),'C2':dict(m=3,p=25,r=10,band='S',cost=90),'C3':dict(m=5,p=60,r=50,band='X',cost=170)}
# ground: contact min/day per orbit
GS={'G1':dict(bands='SX',cost=120,c=dict(O1=80,O2=80,O3=0)),
    'G2':dict(bands='S',cost=40,c=dict(O1=32,O2=32,O3=40)),
    'G3':dict(bands='SX',cost=70,c=dict(O1=24,O2=24,O3=56)),
    'G4':dict(bands='SX',cost=200,c=dict(O1=96,O2=96,O3=96))}
ORB={'O1':dict(h=500,sunfrac=None,decay=84,deorbit=False),
     'O2':dict(h=600,sunfrac=0.98,decay=999,deorbit=True),
     'O3':dict(h=450,sunfrac=None,decay=36,deorbit=False)}
for k,o in ORB.items():
    T,n,e=orbit(o['h']); o['T']=round(T,1); o['n']=round(n,1); o['ecl']=round(e*T,1) if o['sunfrac'] is None else 2.0
    o['sunh']=round((1-e)*24,1) if o['sunfrac'] is None else round(o['sunfrac']*24,1)
print(ORB)
LV={'L1':dict(cost=120,cap=dict(O1=60)),
    'L2':dict(cost=300,cap=dict(O1=150,O2=130,O3=220)),
    'L3':dict(cost=210,cap=dict(O1=220,O2=200))}
OS={'LAND':dict(O1=1.0,O2=.6,O3=.7),'COAST':dict(O1=1.0,O2=.6,O3=.8),'CLOUD':dict(O1=.85,O2=.9,O3=1.0)}
REQ={'LAND':4000,'COAST':8000,'CLOUD':2500}
DIFF={'Beginner':dict(budget=1300,life=12),'Standard':dict(budget=1150,life=24),'Challenge':dict(budget=1075,life=36)}
EXTRA={'STO':dict(m=2,p=5,sto=64000,cost=40),'DEO':dict(m=2,cost=25)}
def evalu(obj,diff,b,ins,p,c,gs,o,l,img,sto,deo):
    B=BUS[b];P=PWR[p];C=COM[c];O=ORB[o];D=DIFF[diff]
    hard=[]
    if len(ins)>B['slots']: hard.append('slots')
    pm=sum(INS[i]['m'] for i in ins)
    if pm>B['cap']: hard.append('paymass')
    if p=='P3' and b=='S': hard.append('p3s')
    if o not in LV[l]['cap']: hard.append('lvorb'); return None
    mass=B['m']+pm+P['m']+C['m']+(2 if sto else 0)+(2 if deo else 0)
    if mass>LV[l]['cap'][o]: hard.append('mass')
    peak=B['hk']+sum(INS[i]['p'] for i in ins)+C['p']+(5 if sto else 0)
    if peak>B['peak']: hard.append('peak')
    contact=0
    for g in gs:
        if C['band'] in GS[g]['bands']: contact+=GS[g]['c'][o]
        else: hard.append('band')
    if contact==0: hard.append('nocontact')
    duty=img/O['T']
    avg=B['hk']+sum(INS[i]['p'] for i in ins)*duty+C['p']*contact/1440+(5 if sto else 0)
    demand=avg*24; gen=P['gen']*O['sunh']
    if gen<demand: hard.append('energy')
    if avg*O['ecl']/60>P['bat']*0.5: hard.append('battery')
    cost=B['cost']+sum(INS[i]['cost'] for i in ins)+P['cost']+C['cost']+sum(GS[g]['cost'] for g in gs)+LV[l]['cost']+(40 if sto else 0)+(25 if deo else 0)
    if cost>D['budget']: hard.append('budget')
    if hard: return None
    gend=sum(INS[i]['r'] for i in ins)*img*60*O['n']/8
    down=C['r']*contact*60/8
    # monthly sim
    storage=B['sto']+(64000 if sto else 0)
    months=min(B['life'],O['decay'],D['life'])
    # energy degradation .25%/month
    em=None
    for mth in range(1,D['life']+1):
        if P['gen']*O['sunh']*(1-0.0025*mth)<demand: em=mth-1;break
    if em is not None: months=min(months,em)
    backlog=0;deliv=0
    for mth in range(1,months+1):
        if mth==1: continue # commissioning
        g=gend*30; d=down*30
        avail=backlog+g
        sent=min(avail,d); deliv+=sent
        backlog=min(storage,avail-sent)
    sciencemonths=D['life']-1
    df=min(1,(deliv/(30*max(1,months-1)))/REQ[obj]) if months>1 else 0
    ss=sorted([INS[i]['s'][obj] for i in ins],reverse=True)
    suit=min(1,ss[0]+(0.25*ss[1] if len(ss)>1 else 0))
    lf=min(1,months/D['life'])
    sci=100*suit*OS[obj][o]*df*lf
    return dict(sci=round(sci,1),cost=cost,mass=mass,gend=gend,down=down,months=months,suit=suit,df=round(df,2))
def enum(obj,diff):
    res=[]
    inscombos=[(i,) for i in INS]+list(itertools.combinations(INS,2))
    gscombos=[(g,) for g in GS]+list(itertools.combinations(GS,2))
    for b in BUS:
      for ins in inscombos:
        for p in PWR:
          for c in COM:
            for gs in gscombos:
              for o in ORB:
                for l in LV:
                  for img in (5,10,15):
                    for sto in (0,1):
                      for deo in (0,1):
                        r=evalu(obj,diff,b,ins,p,c,gs,o,l,img,sto,deo)
                        if r: res.append((r['sci'],r['cost'],b,ins,p,c,gs,o,l,img,sto,deo,r))
    return res
if __name__=='__main__':
  for diff in DIFF:
    for obj in REQ:
        res=enum(obj,diff); res.sort(key=lambda x:(-x[0],x[1]))
        n=len(res); s75=sum(1 for r in res if r[0]>=75); s50=sum(1 for r in res if 50<=r[0]<75)
        print(diff,obj,'feasible',n,'success',s75,'partial',s50,'best',res[0][:12] if res else None)
  import collections
  for diff in DIFF:
    for obj in REQ:
        res=[r for r in enum(obj,diff) if r[0]>=75]
        print(diff,obj,'bus',dict(collections.Counter(r[2] for r in res)),'orb',dict(collections.Counter(r[7] for r in res)),'cheapest success',min(res,key=lambda x:x[1])[:12] if res else None)
  # worked reference designs
  for args in [('LAND','Standard','M',('MSI',),'P2','C2',('G1',),'O1','L3',5,0,0),
               ('LAND','Standard','M',('MSI',),'P2','C2',('G2',),'O1','L3',10,0,0),
               ('COAST','Standard','M',('HSI',),'P2','C3',('G3',),'O1','L3',5,0,0),
               ('CLOUD','Standard','S',('WFC',),'P1','C1',('G2',),'O3','L2',15,0,0),
               ('CLOUD','Standard','M',('TIR',),'P1','C2',('G2',),'O2','L3',15,0,1)]:
      print(args, evalu(*args))
