import {useEffect,useState,type ReactNode} from 'react';
import {Copy,Check,Send,MessageCircle,Mail,Link2,Star,CheckCircle2,CalendarDays,MapPin} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Dialog,DialogContent,DialogTitle,DialogDescription} from '@/components/ui/dialog';
import {api,login} from '@/lib/api';
import type {Usher} from '@/lib/demo-data';
import {Avatar} from './workspace';
type PublicProject={name:string;date:string;location:string;callTime:string;endTime:string;compensation:string;transport:string;dressCode:string};
type Named={id:number;name:string};

export function ShareLinkButton({path,title,description,label,message,variant='default',disabled}:{path:string;title:string;description:string;label:string;message:string;variant?:'default'|'outline';disabled?:boolean}){
  const [open,setOpen]=useState(false);const [copied,setCopied]=useState(false);
  const url=typeof window!=='undefined'?`${window.location.origin}${path}`:path;
  const text=`${message} ${url}`;
  const copy=async()=>{try{await navigator.clipboard.writeText(url);}catch{const t=document.createElement('textarea');t.value=url;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();}setCopied(true);setTimeout(()=>setCopied(false),2000);};
  const nativeShare=async()=>{if(navigator.share){try{await navigator.share({title,text:message,url});}catch{/* cancelled */}}else copy();};
  return <><Button variant={variant} disabled={disabled} onClick={()=>setOpen(true)}><Link2/>{label}</Button>
  <Dialog open={open} onOpenChange={setOpen}><DialogContent><DialogTitle>{title}</DialogTitle><DialogDescription>{description}</DialogDescription>
    <div className="share-url"><input readOnly aria-label="Shareable link" value={url} onFocus={e=>e.currentTarget.select()}/><Button onClick={copy}>{copied?<Check/>:<Copy/>}{copied?'Copied':'Copy link'}</Button></div>
    <div className="share-grid">
      <Button variant="outline" asChild><a href={`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(message)}`} target="_blank" rel="noreferrer"><Send/>Telegram</a></Button>
      <Button variant="outline" asChild><a href={`https://wa.me/?text=${encodeURIComponent(text)}`} target="_blank" rel="noreferrer"><MessageCircle/>WhatsApp</a></Button>
      <Button variant="outline" asChild><a href={`mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(text)}`}><Mail/>Email</a></Button>
      <Button variant="outline" onClick={nativeShare}><Link2/>More options</Button>
    </div>
  </DialogContent></Dialog></>;
}

export function PublicShell({children}:{children:ReactNode}){return <div className="public-shell"><header className="public-header"><img src="/validity-events-logo.svg" alt="Validity Events" className="logo"/></header><main className="public-main">{children}</main><footer className="public-footer">© 2026 Validity Event & Marketing</footer></div>}

function Section({title,children}:{title:string;children:ReactNode}){return <fieldset className="form-section"><legend>{title}</legend><div className="form-grid">{children}</div></fieldset>}
function F({label,name,type='text',required,full,options,placeholder}:{label:string;name:string;type?:string;required?:boolean;full?:boolean;options?:string[];placeholder?:string}){return <label className={full?'full':''}>{label}{required&&<span className="text-primary"> *</span>}{options?<select name={name} required={required} defaultValue=""><option value="" disabled>Select…</option>{options.map(o=><option key={o}>{o}</option>)}</select>:type==='textarea'?<textarea name={name} rows={3} placeholder={placeholder}/>:<input name={name} type={type} required={required} placeholder={placeholder} accept={type==='file'?'image/*':undefined}/>}</label>}
function Checks({label,name,items}:{label:string;name:string;items:string[]}){return <div className="full"><div className="check-label">{label}</div><div className="check-grid">{items.map(i=><label key={i} className="check-item"><input type="checkbox" name={`${name}[]`} value={i}/>{i}</label>)}</div></div>}

export function RegistrationPage({token}:{token:string}){
  const [done,setDone]=useState(false);const [exp,setExp]=useState(1);const [refs,setRefs]=useState(1);const [error,setError]=useState('');
  if(done)return <PublicShell><div className="public-card center"><CheckCircle2 className="big-icon"/><h1>Registration received</h1><p className="subtext">Thank you! The Validity team will review your profile and contact you once it’s verified.</p></div></PublicShell>;
  return <PublicShell><form className="public-card" onSubmit={e=>{e.preventDefault();setError('');void api(`/api/public/register/${token}`,{method:'POST',body:new FormData(e.currentTarget)}).then(()=>{setDone(true);window.scrollTo(0,0)}).catch(()=>setError('Check the required fields and profile photo, then try again.'))}}>
    <h1>Usher registration</h1><p className="subtext mb-6">Join the Validity usher community. Fields marked * are required. Financial and ID details are private and never shared with clients.</p>
    {error&&<p role="alert" className="text-warning mb-4">{error}</p>}
    <Section title="Personal information"><F label="Full name" name="name" required/><F label="Phone number" name="phone" type="tel" required/><F label="Gender" name="gender" required options={['Female','Male']}/><F label="Date of birth" name="dob" type="date" required/><F label="City" name="city" required options={['Addis Ababa','Adama','Bahir Dar','Hawassa','Mekelle','Dire Dawa','Other']}/><F label="Address" name="address" required/><F label="Email" name="email" type="email"/><F label="Telegram username" name="telegram"/></Section>
    <Section title="Emergency contact"><F label="Contact name" name="ecName" required/><F label="Relationship" name="ecRel" required/><F label="Phone number" name="ecPhone" type="tel" required/></Section>
    <Section title="Education"><F label="Highest education level" name="edu" options={['High school','Diploma','Bachelor’s degree','Master’s degree','Other']}/><F label="Institution" name="inst"/><F label="Field of study" name="field"/></Section>
    <Section title="Employment"><F label="Current occupation" name="occ"/><F label="Employer / company" name="employer"/><F label="Employment status" name="empStatus" options={['Student','Employed','Self-employed','Unemployed','Freelancer']}/></Section>
    <Section title="Languages"><Checks label="Languages you speak" name="languages" items={['Amharic','English','Afaan Oromo','Tigrinya','Somali','Arabic','Other']}/><F label="Other languages" name="otherLang" full/></Section>
    <Section title="Event experience"><F label="Years of event experience" name="years" type="number"/><F label="Number of events worked" name="events" type="number"/>
      {Array.from({length:exp}).map((_,i)=><div key={i} className="full sub-entry"><strong>Experience {i+1}</strong><div className="form-grid"><F label="Event name" name={`exEvent${i}`}/><F label="Client" name={`exClient${i}`}/><F label="Role performed" name={`exRole${i}`}/><F label="Event type" name={`exType${i}`} options={['Corporate Event','Conference','Exhibition','Concert','Wedding','Product Launch','Brand Activation','VIP Event']}/></div></div>)}
      <div className="full"><Button type="button" variant="outline" size="sm" onClick={()=>setExp(n=>n+1)}>+ Add experience</Button></div></Section>
    <Section title="Skills & preferred events"><Checks label="Skills" name="skills" items={['Registration','Guest Relations','VIP Handling','Crowd Management','Hospitality','Brand Ambassador','Ticketing','Check-in','Event Protocol','Public Speaking','Customer Service','Team Leader','Supervisor','Technical Support']}/><Checks label="Preferred event types" name="prefs" items={['Corporate','Conference','Exhibition','Concert','Festival','Product Launch','Brand Activation','Wedding','VIP','Government','Sports','Retail/BTL']}/><F label="General availability" name="availability" full options={['Weekdays','Weekends','Weekdays & weekends','Evenings only']}/></Section>
    <Section title="Clothing sizes"><F label="T-shirt size" name="tshirt" options={['XS','S','M','L','XL','XXL']}/><F label="Shirt size" name="shirt" options={['XS','S','M','L','XL','XXL']}/><F label="Trouser size" name="trouser"/><F label="Shoe size" name="shoe"/></Section>
    <Section title="Photos"><F label="Profile photo" name="photo1" type="file" required/><F label="Additional photo (optional)" name="photo2" type="file"/><F label="Additional photo (optional)" name="photo3" type="file"/></Section>
    <Section title="Payment details (private)"><F label="Preferred payment method" name="pay" options={['Bank transfer','Telebirr']}/><F label="Bank name" name="bank"/><F label="Account holder name" name="holder"/><F label="Account number" name="acct"/><F label="Telebirr number" name="telebirr" type="tel"/></Section>
    <Section title="Identification (private)"><F label="ID type" name="idType" options={['Kebele ID','National ID (Fayda)','Passport','Driver’s licence']}/><F label="ID / passport number" name="idNo"/></Section>
    <Section title="Social media (optional)"><F label="Instagram" name="ig"/><F label="Facebook" name="fb"/><F label="LinkedIn" name="li"/><F label="TikTok" name="tt"/></Section>
    <Section title="References">{Array.from({length:refs}).map((_,i)=><div key={i} className="full sub-entry"><strong>Reference {i+1}</strong><div className="form-grid"><F label="Name" name={`rName${i}`}/><F label="Organization" name={`rOrg${i}`}/><F label="Relationship" name={`rRel${i}`}/><F label="Phone" name={`rPhone${i}`} type="tel"/><F label="Email" name={`rEmail${i}`} type="email"/><F label="Notes" name={`rNotes${i}`}/></div></div>)}<div className="full"><Button type="button" variant="outline" size="sm" onClick={()=>setRefs(n=>n+1)}>+ Add reference</Button></div></Section>
    <label className="check-item mb-5"><input type="checkbox" required/>I confirm the information above is accurate.</label>
    <Button type="submit" size="lg" className="w-full">Submit registration</Button>
  </form></PublicShell>;
}

function ProjectHead({project}:{project:PublicProject|undefined}){if(!project)return null;return <div className="mb-6"><h1>{project.name}</h1><div className="project-meta"><span><CalendarDays/>{project.date}</span><span><MapPin/>{project.location}</span></div></div>}
function EventFacts({project}:{project:PublicProject}){return <div className="profile-detail"><div><small>Call time</small>{project.callTime}</div><div><small>End time</small>{project.endTime}</div><div><small>Compensation</small>{project.compensation}</div><div><small>Transport & lunch</small>{project.transport}</div><div className="col-span-2"><small>Dress code</small>{project.dressCode}</div></div>}

export function RespondPage({id}:{id:string}){
  const [project,setProject]=useState<PublicProject|null>(null);const [invited,setInvited]=useState<Named[]>([]);const [who,setWho]=useState('');const [answer,setAnswer]=useState('');const [error,setError]=useState('');
  useEffect(()=>{void api<{project:PublicProject;ushers:Named[]}>(`/api/public/availability/${id}`).then(data=>{setProject(data.project);setInvited(data.ushers)}).catch(()=>setError('This availability link is not valid.'))},[id]);
  if(answer)return <PublicShell><div className="public-card center"><CheckCircle2 className="big-icon"/><h1>{answer==='Confirmed'?'You’re confirmed!':'Thanks for letting us know'}</h1><p className="subtext">{answer==='Confirmed'?'We’ll share the call time and final details closer to the event.':'We hope to work with you on the next event.'}</p></div></PublicShell>;
  if(error)return <PublicShell><div className="public-card center"><h1>Link not found</h1><p className="subtext">{error}</p></div></PublicShell>;
  if(!project)return <PublicShell><div className="public-card center"><p className="subtext">Loading invitation…</p></div></PublicShell>;
  return <PublicShell><div className="public-card"><ProjectHead project={project}/><h2 className="mb-3">Confirm your availability</h2><EventFacts project={project}/>
    <div className="form-grid mb-5"><label className="full">Your name<select aria-label="Your name" value={who} onChange={e=>setWho(e.target.value)}><option value="">Select your name…</option>{invited.map(u=><option key={u.id} value={u.id}>{u.name}</option>)}</select></label></div>
    <div className="toolbar"><Button disabled={!who} onClick={()=>{void api(`/api/public/availability/${id}`,{method:'POST',body:JSON.stringify({usher_id:Number(who),response:'Confirmed'})}).then(()=>setAnswer('Confirmed'))}}><Check/>I’m available</Button><Button variant="outline" disabled={!who} onClick={()=>{void api(`/api/public/availability/${id}`,{method:'POST',body:JSON.stringify({usher_id:Number(who),response:'Declined'})}).then(()=>setAnswer('Declined'))}}>Not available</Button></div></div></PublicShell>;
}

export function ClientPage({id}:{id:string}){
  const [project,setProject]=useState<PublicProject|null>(null);const [team,setTeam]=useState<Usher[]>([]);const [picked,setPicked]=useState<number[]>([]);const [done,setDone]=useState(false);const [view,setView]=useState<Usher|null>(null);const [error,setError]=useState('');
  useEffect(()=>{void api<{project:PublicProject;ushers:Usher[]}>(`/api/public/client/${id}`).then(data=>{setProject(data.project);setTeam(data.ushers)}).catch(()=>setError('This client link is not valid.'))},[id]);
  if(done)return <PublicShell><div className="public-card center"><CheckCircle2 className="big-icon"/><h1>Selection submitted</h1><p className="subtext">Thank you. Validity will finalise your team of {picked.length} ushers.</p></div></PublicShell>;
  if(error)return <PublicShell><div className="public-card center"><h1>Link not found</h1><p className="subtext">{error}</p></div></PublicShell>;
  if(!project)return <PublicShell><div className="public-card center"><p className="subtext">Loading team…</p></div></PublicShell>;
  return <PublicShell><div className="public-card"><ProjectHead project={project}/><h2 className="mb-1">Select your team</h2><p className="subtext mb-4">These ushers have confirmed their availability. Tick the people you’d like on your team.</p>
    {team.length?team.map(u=><label className="review-row cursor-pointer" key={u.id}><Avatar usher={u}/><div className="flex-1"><h3>{u.name}</h3><div className="subtext">{u.experience} years · {u.events} events · {u.languages.join(', ')}</div><div>{u.skills.map(s=><span key={s} className="skill">{s}</span>)}</div></div><span className="rating"><Star/>{u.rating}</span><Button type="button" variant="outline" size="sm" onClick={e=>{e.preventDefault();setView(u)}}>View profile</Button><input type="checkbox" aria-label={`Select ${u.name}`} checked={picked.includes(u.id)} onChange={()=>setPicked(p=>p.includes(u.id)?p.filter(x=>x!==u.id):[...p,u.id])}/></label>):<div className="empty">No confirmed ushers yet.</div>}
    <Button className="w-full mt-5" disabled={!picked.length} onClick={()=>{void api(`/api/public/client/${id}`,{method:'POST',body:JSON.stringify({usher_ids:picked})}).then(()=>setDone(true))}}><Check/>Submit selection ({picked.length})</Button></div>
    <Dialog open={!!view} onOpenChange={()=>setView(null)}><DialogContent className="max-h-[90vh] overflow-y-auto">{view&&<><DialogTitle>{view.name}</DialogTitle><DialogDescription>{view.city} · {view.gender}</DialogDescription>
      <div className="profile-detail"><div><small>Experience</small><strong>{view.experience} years · {view.events} events</strong></div><div><small>Rating</small><span className="rating"><Star/>{view.rating} / 5</span></div><div><small>Languages</small>{view.languages.join(', ')}</div><div><small>Preferred events</small>{(view.preferredEvents??[]).join(', ')||'—'}</div></div>
      <h3 className="mb-2">Skills</h3><div className="mb-4">{view.skills.map(s=><span key={s} className="skill">{s}</span>)}</div>
      <h3 className="mb-2">Event experience</h3>{(view.experiences??[]).length?(view.experiences??[]).map(item=><div key={`${item.event}-${item.role}`} className="review-row"><div className="flex-1"><strong>{item.event}</strong><div className="subtext">{item.client} · {item.role}</div></div></div>):<p className="subtext">No previous events listed.</p>}
      <Button className="w-full mt-4" onClick={()=>{setPicked(p=>p.includes(view.id)?p:[...p,view.id]);setView(null)}}><Check/>{picked.includes(view.id)?'Selected':'Select for my team'}</Button></>}</DialogContent></Dialog></PublicShell>;
}

export function RatePage({id}:{id:string}){
  const [project,setProject]=useState<PublicProject|null>(null);const [team,setTeam]=useState<Usher[]>([]);const [scores,setScores]=useState<Record<number,number>>({});const [comments,setComments]=useState<Record<number,string>>({});const [done,setDone]=useState(false);const [error,setError]=useState('');
  useEffect(()=>{void api<{project:PublicProject;ushers:Usher[]}>(`/api/public/ratings/${id}`).then(data=>{setProject(data.project);setTeam(data.ushers)}).catch(()=>setError('This rating link is not valid.'))},[id]);
  if(done)return <PublicShell><div className="public-card center"><CheckCircle2 className="big-icon"/><h1>Thank you for your feedback</h1><p className="subtext">Your ratings help us build even better teams.</p></div></PublicShell>;
  if(error)return <PublicShell><div className="public-card center"><h1>Link not found</h1><p className="subtext">{error}</p></div></PublicShell>;
  if(!project)return <PublicShell><div className="public-card center"><p className="subtext">Loading team…</p></div></PublicShell>;
  return <PublicShell><div className="public-card"><ProjectHead project={project}/><h2 className="mb-1">Rate your event team</h2><p className="subtext mb-4">How did each usher perform?</p>
    {team.map(u=><div className="review-row" key={u.id}><Avatar usher={u}/><div className="flex-1"><h3>{u.name}</h3><textarea className="w-full mt-2" rows={2} placeholder="Optional comment" value={comments[u.id]??''} onChange={e=>setComments(c=>({...c,[u.id]:e.target.value}))}/></div><div className="stars" role="radiogroup" aria-label={`Rate ${u.name}`}>{[1,2,3,4,5].map(n=><button type="button" key={n} aria-label={`${n} stars`} className={(scores[u.id]||0)>=n?'on':''} onClick={()=>setScores(s=>({...s,[u.id]:n}))}><Star/></button>)}</div></div>)}
    <Button className="w-full mt-5" disabled={!team.length||Object.keys(scores).length<team.length} onClick={()=>{void api(`/api/public/ratings/${id}`,{method:'POST',body:JSON.stringify({ratings:team.map(u=>({usher_id:u.id,score:scores[u.id],comment:comments[u.id]??''}))})}).then(()=>setDone(true))}}><Check/>Submit ratings</Button></div></PublicShell>;
}

export function LoginPage(){
  const [email,setEmail]=useState('');const [password,setPassword]=useState('');const [error,setError]=useState('');const [pending,setPending]=useState(false);
  return <PublicShell><form className="public-card narrow" onSubmit={e=>{e.preventDefault();setError('');setPending(true);void login(email,password).then(()=>{window.location.assign('/')}).catch(()=>{setError('Those credentials were not recognized.');setPending(false)})}}>
    <h1>Sign in</h1><p className="subtext mb-6">Validity administrators</p>
    {error&&<p role="alert" className="text-warning mb-4">{error}</p>}
    <div className="form-grid"><label className="full">Email<input type="email" required autoComplete="username" value={email} onChange={e=>setEmail(e.target.value)} placeholder="admin@validity.et"/></label><label className="full">Password<input type="password" required autoComplete="current-password" value={password} onChange={e=>setPassword(e.target.value)}/></label><Button type="submit" className="full" disabled={pending}>{pending?'Signing in…':'Sign in'}</Button></div>
  </form></PublicShell>;
}
