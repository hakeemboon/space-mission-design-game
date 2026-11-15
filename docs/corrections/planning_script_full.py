import planning_script_tune as T, itertools
def full(obj,diff,b,ins,p,c,gs,o,l,img,sto,deo):
    B=T.BUS[b];P=T.PWR[p];C=T.COM[c];O=T.ORB[o];D=T.DIFF[diff]
    H=[];W=[]
    pm=sum(T.INS[i]['m'] for i in ins)
    if len(ins)>B['slots']:H.append('H02')
    if pm>B['cap']:H.append('H03')
    if p=='P3' and b=='S':H.append('H04')
    cap=T.LV[l]['cap'].get(o)
    if cap is None:H.append('H05')
    mass=B['m']+pm+P['m']+C['m']+2*sto+2*deo
    if cap is not None and mass>cap:H.append('H06')
    peak=B['hk']+sum(T.INS[i]['p'] for i in ins)+C['p']+5*sto
    if peak>B['peak']:H.append('H07')
    contact=0
    for g in gs:
        if C['band'] in T.GS[g]['bands']: contact+=T.GS[g]['c'][o]
        else: H.append('H08')
    if contact==0:H.append('H09')
    avg=B['hk']+sum(T.INS[i]['p'] for i in ins)*img/O['T']+C['p']*contact/1440+5*sto
    dem=avg*24;gen=P['gen']*O['sunh'];ecl=avg*O['ecl']/60
    if round(gen,2)<round(dem,2):H.append('H10')
    if round(ecl,2)>P['bat']*.5:H.append('H11')
    cost=B['cost']+sum(T.INS[i]['cost'] for i in ins)+P['cost']+C['cost']+sum(T.GS[g]['cost'] for g in gs)+T.LV[l]['cost']+40*sto+25*deo
    if cost>D['budget']:H.append('H12')
    gend=sum(T.INS[i]['r'] for i in ins)*img*60*O['n']/8; down=C['r']*contact*60/8
    out=dict(mass=mass,cap=cap,peak=peak,avg=round(avg,2),dem=round(dem,2),gen=round(gen,2),ecl=round(ecl,2),cost=cost,gend=round(gend,2),down=round(down,2),H=sorted(set(H)))
    if H: return out
    sto_mb=B['sto']+64000*sto
    em=None
    for m in range(1,D['life']+1):
        if round(gen*(1-0.0025*m),2)<round(dem,2): em=m-1;break
    lims=[('target',D['life']),('bus',B['life']),('decay',O['decay'])]+([('energy',em)] if em is not None else [])
    L=min(v for _,v in lims)
    order=['energy','bus','decay','target']
    reason=[k for k in order if dict(lims).get(k)==L][0]
    back=0;deliv=0;lost=0;firstlost=None
    for m in range(2,L+1):
        av=back+gend*30;sent=min(av,down*30);rem=av-sent
        lo=max(0,rem-sto_mb);back=min(sto_mb,rem);deliv+=sent;lost+=lo
        if lo>0 and firstlost is None:firstlost=m
    df=min(1,(deliv/(30*(L-1)))/T.REQ[obj]) if L>1 else 0
    ss=sorted([T.INS[i]['s'][obj] for i in ins],reverse=True)
    fi=min(1,ss[0]+(0.25*ss[1] if len(ss)>1 else 0)); fo=T.OS[obj][o]; fl=min(1,L/D['life'])
    sci=100*fi*fo*df*fl
    eff=50*min(1,(D['budget']-cost)/(0.2*D['budget']))+50*min(1,(cap-mass)/(0.2*cap))
    disp=(O['decay']<=300) or deo
    resp=(60 if disp else 0)+(40 if lost==0 else 0)
    ms=.7*sci+.2*eff+.1*resp
    if (cap-mass)<0.1*cap:W.append('W01')
    if gen<1.15*dem:W.append('W02')
    if gend>down:W.append('W03')
    if lost>0:W.append('W04')
    if O['deorbit'] and not deo:W.append('W05')
    if L<D['life']:W.append('W06')
    if ss[0]<.8:W.append('W07')
    if fo<.8:W.append('W08')
    if min(gend,down)<T.REQ[obj]:W.append('W09')
    out.update(L=L,reason=reason,deliv=round(deliv),lost=round(lost),firstlost=firstlost,fi=round(fi,2),fo=fo,fd=round(df,4),fl=round(fl,4),sci=round(sci,2),eff=round(eff,2),resp=resp,ms=round(ms,2),W=W,
      band='SUCCESS' if round(sci,2)>=75 else 'PARTIAL' if round(sci,2)>=50 else 'NOT_MET')
    return out
if __name__=='__main__':
  cases=[
  ('TC-A',('LAND','Standard','M',('MSI',),'P2','C2',('G1',),'O1','L3',5,0,0)),
  ('TC-B1',('LAND','Standard','S',('SWIR',),'P1','C2',('G1',),'O1','L1',10,0,0)),
  ('TC-B2',('LAND','Standard','S',('SWIR',),'P1','C1',('G1',),'O1','L1',10,0,0)),
  ('TC-C',('CLOUD','Challenge','M',('TIR',),'P1','C2',('G2',),'O2','L3',15,0,1)),
  ('TC-coastG1',('COAST','Standard','M',('HSI',),'P2','C3',('G1',),'O1','L3',5,0,0)),
  ('TC-coastG3',('COAST','Standard','M',('HSI',),'P2','C3',('G3',),'O1','L3',5,0,0)),
  ('TC-coastG3STO',('COAST','Standard','M',('HSI',),'P2','C3',('G3',),'O1','L3',5,1,0)),
  ('TC-cloudref',('CLOUD','Standard','M',('TIR',),'P2','C2',('G2',),'O3','L2',15,0,0)),
  ('TC-small',('CLOUD','Standard','S',('WFC',),'P1','C2',('G2',),'O3','L2',15,0,0)),
  ('TC-smallChal',('CLOUD','Challenge','S',('WFC',),'P1','C2',('G2',),'O3','L2',15,0,0)),
  ('TC-nodeo',('CLOUD','Standard','M',('TIR',),'P2','C2',('G2',),'O2','L3',15,0,0)),
  ('TC-twoinst',('LAND','Standard','M',('WFC','SWIR'),'P2','C2',('G1',),'O1','L3',5,0,0)),
  ('TC-H02',('LAND','Standard','S',('WFC','SWIR'),'P1','C1',('G2',),'O1','L1',5,0,0)),
  ('TC-H03',('COAST','Standard','S',('HSI',),'P1','C1',('G2',),'O1','L1',5,0,0)),
  ('TC-H04',('CLOUD','Standard','S',('WFC',),'P3','C1',('G2',),'O1','L1',5,0,0)),
  ('TC-H05',('CLOUD','Standard','S',('WFC',),'P1','C1',('G2',),'O3','L1',5,0,0)),
  ('TC-H06',('LAND','Standard','M',('MSI',),'P2','C2',('G1',),'O1','L1',5,0,0)),
  ('TC-H08',('COAST','Standard','M',('HSI',),'P2','C3',('G2',),'O1','L3',5,0,0)),
  ('TC-H09',('CLOUD','Standard','M',('TIR',),'P2','C2',('G1',),'O3','L2',15,0,0)),
  ('TC-H10',('LAND','Standard','M',('MSI',),'P1','C2',('G1',),'O1','L3',5,0,0)),
  ('TC-H11',('LAND','Beginner','L',('MSI',),'P1','C2',('G1',),'O1','L3',5,0,0)),
  ('TC-H12',('COAST','Challenge','M',('HSI',),'P2','C3',('G1',),'O1','L3',5,0,0)),
  ('TC-massEq',('CLOUD','Beginner','L',('WFC',),'P3','C3',('G3',),'O1','L2',5,1,0)),
  ('TC-peakEq',('COAST','Standard','M',('HSI',),'P2','C3',('G3',),'O1','L3',5,1,0)),
  ('TC-W10',('CLOUD','Standard','M',('TIR',),'P2','C2',('G1','G2'),'O3','L2',15,0,0)),
  ]
  for n,a in cases: print(n,a[2:],full(*a))
  # budget-equal search
  for diff in T.DIFF:
    found=0
    for b in T.BUS:
      for ins in [(i,) for i in T.INS]+list(itertools.combinations(T.INS,2)):
        for p in T.PWR:
          for c in T.COM:
            for gs in [(g,) for g in T.GS]+list(itertools.combinations(T.GS,2)):
              for o in T.ORB:
                for l in T.LV:
                  for sto in (0,1):
                    for deo in (0,1):
                      r=full('CLOUD',diff,b,ins,p,c,gs,o,l,5,sto,deo)
                      if r['cost']==T.DIFF[diff]['budget'] and not r['H'] and found<1:
                          found+=1;print('BUDGET-EQ',diff,b,ins,p,c,gs,o,l,sto,deo,r)
