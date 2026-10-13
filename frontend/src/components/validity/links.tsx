import {useEffect,useRef,useState,type HTMLAttributes,type ReactNode} from 'react';
import {Copy,Check,Send,MessageCircle,Mail,Link2,Star,CheckCircle2,CalendarDays,MapPin} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Dialog,DialogContent,DialogTitle,DialogDescription} from '@/components/ui/dialog';
import {ApiError,api,login} from '@/lib/api';
import type {Usher} from '@/lib/demo-data';
import {Avatar} from './workspace';
import {LoadingMark,UsherPhotos} from './brand';
type PublicProject={name:string;date:string;location:string;callTime:string;endTime:string;compensation:string;transport:string;dressCode:string;transportProvided?:boolean;foodProvided?:boolean};
type Named={id:number;name:string};

export function ShareLinkButton({path,title,description,label,message,variant='default',size='default',disabled}:{path:string;title:string;description:string;label:string;message:string;variant?:'default'|'outline';size?:'default'|'sm';disabled?:boolean}){
  const [open,setOpen]=useState(false);const [copied,setCopied]=useState(false);
  const url=typeof window!=='undefined'?`${window.location.origin}${path}`:path;
  const text=`${message} ${url}`;
  const copy=async()=>{try{await navigator.clipboard.writeText(url);}catch{const t=document.createElement('textarea');t.value=url;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();}setCopied(true);setTimeout(()=>setCopied(false),2000);};
  const nativeShare=async()=>{if(navigator.share){try{await navigator.share({title,text:message,url});}catch{/* cancelled */}}else copy();};
  return <><Button variant={variant} size={size} disabled={disabled} onClick={()=>setOpen(true)}><Link2/>{label}</Button>
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

export function PublicShell({children,variant}:{children:ReactNode;variant?:'reg'|'client'}){return <div className={`public-shell${variant?` public-shell-${variant}`:''}`}>{variant!=='reg'&&<header className="public-header"><img src="/validity-events-logo.svg" alt="Validity Events" className="logo"/></header>}<main className="public-main">{children}</main><footer className="public-footer">© 2026 Validity Events</footer></div>}

function Section({title,children}:{title:string;children:ReactNode}){return <fieldset className="form-section"><legend>{title}</legend><div className="form-grid">{children}</div></fieldset>}
function adultDobMax(){const d=new Date();d.setFullYear(d.getFullYear()-18);return d.toISOString().slice(0,10)}
function F({label,name,type='text',required,full,options,placeholder,max,title,pattern,inputMode}:{label:string;name:string;type?:string;required?:boolean;full?:boolean;options?:string[];placeholder?:string;max?:string;title?:string;pattern?:string;inputMode?:HTMLAttributes<HTMLInputElement>['inputMode']}){return <label className={full?'full':''}>{label}{required&&<span className="text-primary"> *</span>}{options?<select name={name} required={required} defaultValue=""><option value="" disabled>Select…</option>{options.map(o=><option key={o}>{o}</option>)}</select>:type==='textarea'?<textarea name={name} rows={3} placeholder={placeholder}/>:<input name={name} type={type} required={required} placeholder={placeholder} max={max} title={title} pattern={pattern} inputMode={inputMode}/>}</label>}
function Checks({label,name,items}:{label:string;name:string;items:string[]}){return <div className="full"><div className="check-label">{label}</div><div className="check-grid">{items.map(i=><label key={i} className="check-item"><input type="checkbox" name={`${name}[]`} value={i}/>{i}</label>)}</div></div>}
function AddMore({label,children}:{label:string;children:ReactNode}){const [open,setOpen]=useState(false);return <div className="reg-add-more full"><button type="button" className="reg-add-more-toggle" aria-expanded={open} onClick={()=>setOpen(v=>!v)}>{open?'Hide':'Add more'} — {label}</button>{open&&<div className="reg-add-more-body form-grid">{children}</div>}</div>}
function PhotoPicker({files,onChange,required}:{files:File[];onChange:(files:File[])=>void;required?:boolean}){
  const [note,setNote]=useState('');
  return <div className="full reg-photos">
    <label>Photos{required&&<span className="text-primary"> *</span>}<span className="subtext"> — select up to 3 at once; first is your profile photo</span>
      <input type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple required={required&&files.length===0} onChange={e=>{
        const picked=Array.from(e.target.files??[]).filter(f=>f.type.startsWith('image/'));
        setNote(picked.length>3?'Only the first 3 images were kept.':'');
        onChange(picked.slice(0,3));
      }}/>
    </label>
    {files.length>0&&<ul className="reg-photo-list">{files.map((file,index)=><li key={`${file.name}-${file.size}-${index}`}><span>{index===0?'Profile':'Extra'} — {file.name}</span><button type="button" className="reg-photo-remove" onClick={()=>onChange(files.filter((_,i)=>i!==index))}>Remove</button></li>)}</ul>}
    {note&&<p className="subtext">{note}</p>}
  </div>;
}

const fieldLabels:Record<string,string>={name:'Full name',phone:'Phone number',gender:'Gender',dob:'Date of birth',city:'City',address:'Address',email:'Email',telegram:'Telegram username',ecName:'Emergency contact name',ecRel:'Emergency contact relationship',ecPhone:'Emergency contact phone',holder:'Account holder name',acct:'CBE account number',telebirr:'Telebirr number',idType:'ID type',idNo:'ID / passport number',idPhoto:'ID photo',photo1:'Profile photo',photo2:'Additional photo',photo3:'Additional photo',confirmAccuracy:'Accuracy confirmation'};
const fieldStep:(field:string)=>string=(field)=>{
  if(['name','phone','gender','dob','city','address','email','telegram'].includes(field))return 'About you';
  if(['ecName','ecRel','ecPhone'].includes(field))return 'Emergency';
  if(['edu','inst','field','occ','employer','empStatus'].includes(field))return 'Background';
  if(field.startsWith('photo')||['tshirt','shirt','trouser','shoe'].includes(field))return 'Photos & sizes';
  if(['bank','holder','acct','telebirr','idType','idNo','idPhoto'].includes(field))return 'Private details';
  return 'Confirm';
};
function fieldMessage(field:HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement){
  const label=fieldLabels[field.name]||field.name||'This field';
  if(field.validity.valueMissing)return `${label} is required to continue.`;
  if(field.validity.typeMismatch)return field.type==='email'?`Enter a valid email address.`:`Enter a valid ${label.toLowerCase()}.`;
  if(field.validity.patternMismatch&&field.name==='acct')return 'CBE account numbers must start with 1000 and contain digits only.';
  if(field.validity.patternMismatch)return `${label} is not in the correct format.`;
  if(field.name==='dob'&&(field.validity.rangeOverflow||field.validity.rangeUnderflow))return 'Ushers must be 18 or older.';
  if(field.validity.rangeOverflow||field.validity.rangeUnderflow)return `${label} is out of range.`;
  if(field.validity.tooShort)return `${label} is too short.`;
  if(field.validity.tooLong)return `${label} is too long.`;
  if(field.validity.badInput)return `Enter a valid value for ${label.toLowerCase()}.`;
  return field.validationMessage||`Please check ${label.toLowerCase()}.`;
}

export function RegistrationPage({token}:{token:string}){
  const formRef=useRef<HTMLFormElement>(null);
  const errorRef=useRef<HTMLParagraphElement>(null);
  const [done,setDone]=useState(false);const [step,setStep]=useState(0);const [exp,setExp]=useState(1);const [photos,setPhotos]=useState<File[]>([]);const [idPhoto,setIdPhoto]=useState<File|null>(null);const [error,setError]=useState('');const [submitting,setSubmitting]=useState(false);
  const steps:[string,ReactNode][]=[
    ['About you',<Section title="About you"><F label="Full name" name="name" required/><F label="Phone number" name="phone" type="tel" required/><F label="Gender" name="gender" required options={['Female','Male']}/><F label="Date of birth" name="dob" type="date" required max={adultDobMax()} title="You must be 18 or older"/><p className="full subtext">Ushers must be 18 or older.</p><F label="City" name="city" required options={['Addis Ababa','Adama','Bahir Dar','Hawassa','Mekelle','Dire Dawa','Other']}/><F label="Address" name="address" required full/><AddMore label="email & Telegram"><F label="Email" name="email" type="email"/><F label="Telegram username" name="telegram"/></AddMore></Section>],
    ['Emergency',<Section title="Emergency contact"><F label="Contact name" name="ecName" required full/><F label="Relationship" name="ecRel" required/><F label="Phone number" name="ecPhone" type="tel" required/></Section>],
    ['Background',<><Section title="Education"><F label="Highest education level" name="edu" options={['High school','Diploma','Bachelor’s degree','Master’s degree','Other']}/><F label="Institution" name="inst"/><F label="Field of study" name="field" full/></Section><Section title="Employment"><F label="Current occupation" name="occ"/><F label="Employer / company" name="employer"/><F label="Employment status" name="empStatus" full options={['Student','Employed','Self-employed','Unemployed','Freelancer']}/></Section></>],
    ['Languages & skills',<><Section title="Languages"><Checks label="Languages you speak" name="languages" items={['Amharic','English','Afaan Oromo','Tigrinya','Somali','Arabic','Other']}/><F label="Other languages" name="otherLang" full/></Section><Section title="Skills & preferred events"><Checks label="Skills" name="skills" items={['Registration','Guest Relations','VIP Handling','Crowd Management','Hospitality','Brand Ambassador','Ticketing','Check-in','Event Protocol','Public Speaking','Customer Service','Team Leader','Supervisor','Technical Support']}/><Checks label="Preferred event types" name="prefs" items={['Corporate','Conference','Exhibition','Concert','Festival','Product Launch','Brand Activation','Wedding','VIP','Government','Sports','Retail/BTL']}/><F label="General availability" name="availability" full options={['Weekdays','Weekends','Weekdays & weekends','Evenings only']}/></Section></>],
    ['Experience',<Section title="Event experience"><F label="Years of event experience" name="years" type="number"/><F label="Number of events worked" name="events" type="number"/>
      {Array.from({length:exp}).map((_,i)=><div key={i} className="full sub-entry"><strong>Experience {i+1}</strong><div className="form-grid"><F label="Event name" name={`exEvent${i}`}/><F label="Client" name={`exClient${i}`}/><F label="Role performed" name={`exRole${i}`}/><F label="Event type" name={`exType${i}`} options={['Corporate Event','Conference','Exhibition','Concert','Wedding','Product Launch','Brand Activation','VIP Event']}/></div></div>)}
      <div className="full"><Button type="button" variant="outline" size="sm" onClick={()=>setExp(n=>n+1)}>+ Add experience</Button></div></Section>],
    ['Photos & sizes',<><Section title="Photos"><p className="full subtext">Choose up to 3 photos from your gallery. The first image is used as your profile photo.</p><PhotoPicker files={photos} onChange={setPhotos} required/></Section><Section title="Clothing sizes"><F label="T-shirt size" name="tshirt" options={['XS','S','M','L','XL','XXL']}/><F label="Shirt size" name="shirt" options={['XS','S','M','L','XL','XXL']}/><F label="Trouser size" name="trouser"/><F label="Shoe size" name="shoe"/></Section></>],
    ['Private details',<><Section title="Payment details (private)"><p className="full subtext">Only Validity administrators can see this. It is never shared with clients.</p><div className="full"><small className="text-muted-foreground">Bank</small><strong>CBE</strong><input type="hidden" name="bank" value="CBE"/></div><F label="Account holder name" name="holder" required full/><F label="CBE account number" name="acct" required full placeholder="1000…" pattern="1000[0-9]+" inputMode="numeric" title="Must start with 1000 and contain digits only"/><p className="full subtext">CBE account numbers start with 1000.</p><F label="Telebirr number (optional)" name="telebirr" type="tel" full placeholder="Usually your phone number"/><p className="full subtext">Optional — add a Telebirr number if you also want to be paid that way.</p></Section><Section title="Identification (private)"><p className="full subtext">Kept private and used only to verify your profile. Clients never see this.</p><F label="ID type" name="idType" required options={['Kebele ID','National ID (Fayda)','Passport','Driver’s licence']} full/><F label="ID / passport number" name="idNo" full/><div className="full reg-photos"><label>ID photo<span className="text-primary"> *</span><span className="subtext"> — clear photo of your ID document</span><input type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required={!idPhoto} onChange={e=>{const file=e.target.files?.[0]??null;setIdPhoto(file&&file.type.startsWith('image/')?file:null)}}/></label>{idPhoto&&<ul className="reg-photo-list"><li><span>ID — {idPhoto.name}</span><button type="button" className="reg-photo-remove" onClick={()=>setIdPhoto(null)}>Remove</button></li></ul>}</div></Section></>],
    ['Confirm',<div className="reg-confirm"><p>You’re almost done. Confirm your details are accurate, then submit.</p><label className="check-item"><input type="checkbox" name="confirmAccuracy" required/>I confirm the information above is accurate.</label><AddMore label="social links"><F label="Instagram" name="ig"/><F label="Facebook" name="fb"/><F label="LinkedIn" name="li"/><F label="TikTok" name="tt"/></AddMore></div>],
  ];
  const showError=(message:string)=>{setError(message);requestAnimationFrame(()=>errorRef.current?.scrollIntoView({behavior:'smooth',block:'nearest'}));};
  const showStep=(index:number,clearError=true)=>{setStep(index);if(clearError)setError('');const body=formRef.current?.querySelector('.reg-body');if(body)body.scrollTop=0;};
  const paneValid=(index:number)=>{
    if(steps[index]?.[0]==='Photos & sizes'&&photos.length===0){showError('Add at least one profile photo to continue.');return false;}
    if(steps[index]?.[0]==='Private details'&&!idPhoto){showError('Attach a clear photo of your ID to continue.');return false;}
    const pane=formRef.current?.querySelectorAll<HTMLElement>('[data-step]')[index];
    if(!pane)return true;
    const wasHidden=pane.hidden;
    pane.hidden=false;
    const fields=pane.querySelectorAll<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>('input,select,textarea');
    for(const field of fields){
      if(field.checkValidity())continue;
      field.reportValidity();
      showError(field.name==='confirmAccuracy'?'Please confirm your details are accurate to submit.':fieldMessage(field));
      try{field.focus({preventScroll:false});}catch{/* ignore */}
      pane.hidden=wasHidden;
      return false;
    }
    pane.hidden=wasHidden;
    setError('');
    return true;
  };
  const submitRegistration=async(form:HTMLFormElement)=>{
    if(photos.length===0){showError('Add at least one profile photo to continue.');showStep(steps.findIndex(([title])=>title==='Photos & sizes'),false);return;}
    if(!idPhoto){showError('Attach a clear photo of your ID to continue.');showStep(steps.findIndex(([title])=>title==='Private details'),false);return;}
    setError('');setSubmitting(true);
    const body=new FormData(form);
    body.delete('confirmAccuracy');
    (['photo1','photo2','photo3','idPhoto'] as const).forEach(key=>body.delete(key));
    photos.slice(0,3).forEach((file,index)=>body.append(`photo${index+1}`,file));
    body.append('idPhoto',idPhoto);
    try{
      await api(`/api/public/register/${token}`,{method:'POST',body});
      setDone(true);window.scrollTo(0,0);
    }catch(err){
      if(err instanceof ApiError){
        const field=err.firstField();
        const detail=err.firstError();
        showError(detail||err.message||'Registration could not be submitted. Please check your details and try again.');
        if(field){
          const target=steps.findIndex(([title])=>title===fieldStep(field));
          if(target>=0)showStep(target,false);
        }
      }else{
        showError('Something went wrong. Check your connection and try again.');
      }
    }finally{
      setSubmitting(false);
    }
  };
  if(done)return <PublicShell variant="reg"><div className="reg-success"><CheckCircle2 className="big-icon"/><h1>Registration received</h1><p className="subtext">Thank you! The Validity team will review your profile and contact you once it’s verified.</p></div></PublicShell>;
  return <PublicShell variant="reg"><form ref={formRef} className="reg-form" onSubmit={e=>{e.preventDefault();if(submitting||!paneValid(step))return;void submitRegistration(e.currentTarget);}}>
    <div className="reg-top"><img src="/validity-events-logo.svg" alt="Validity Events" className="reg-logo"/><p className="reg-tagline">Join the usher community</p><div className="reg-progress"><span>Step {step+1} of {steps.length}</span><strong>{steps[step][0]}</strong><div className="reg-bar" aria-hidden="true"><span style={{width:`${((step+1)/steps.length)*100}%`}}/></div></div></div>
    {error&&<p ref={errorRef} role="alert" aria-live="assertive" className="reg-error">{error}</p>}
    <div className="reg-body">{steps.map(([title,node],index)=><div key={title} data-step className={index===step?'reg-step active':''} hidden={index!==step}>{node}</div>)}</div>
    <div className="reg-nav">{step>0&&<Button type="button" variant="outline" onClick={()=>showStep(step-1)}>Back</Button>}{step<steps.length-1?<Button type="button" onClick={()=>{if(paneValid(step))showStep(step+1);}}>Continue</Button>:<Button type="submit" size="lg" pending={submitting}>Submit registration</Button>}</div>
  </form></PublicShell>;
}

function ProjectHead({project}:{project:PublicProject|undefined}){if(!project)return null;return <div className="mb-6"><h1>{project.name}</h1><div className="project-meta"><span><CalendarDays/>{project.date}</span><span><MapPin/>{project.location}</span></div></div>}
function provided(value:boolean|undefined){return value===false?'Not provided':'Provided'}
function EventFacts({project}:{project:PublicProject}){return <div className="profile-detail"><div><small>Dates</small>{project.date}</div><div><small>Work hours</small>{project.callTime} – {project.endTime}</div><div><small>Transport</small>{provided(project.transportProvided)}</div><div><small>Food</small>{provided(project.foodProvided)}</div><div><small>Compensation</small>{project.compensation}</div><div><small>Dress code</small>{project.dressCode}</div></div>}

export function RespondPage({id}:{id:string}){
  const [project,setProject]=useState<PublicProject|null>(null);const [invited,setInvited]=useState<Named[]>([]);const [who,setWho]=useState('');const [answer,setAnswer]=useState('');const [error,setError]=useState('');const [note,setNote]=useState('');
  useEffect(()=>{void api<{project:PublicProject;ushers:Named[]}>(`/api/public/availability/${id}`).then(data=>{setProject(data.project);setInvited(data.ushers)}).catch(()=>setError('This availability link is not valid.'))},[id]);
  if(answer)return <PublicShell><div className="public-card center"><CheckCircle2 className="big-icon"/><h1>{answer==='Confirmed'?'You’re confirmed!':'Thanks for letting us know'}</h1><p className="subtext">{answer==='Confirmed'?'We’ll share the call time and final details closer to the event.':'We hope to work with you on the next event.'}</p></div></PublicShell>;
  if(error)return <PublicShell><div className="public-card center"><h1>Link not found</h1><p className="subtext">{error}</p></div></PublicShell>;
  if(!project)return <PublicShell><LoadingMark label="Loading invitation…" /></PublicShell>;
  return <PublicShell><div className="public-card"><ProjectHead project={project}/><h2 className="mb-3">Confirm your availability</h2><EventFacts project={project}/>
    <div className="form-grid mb-5"><label className="full">Your name<select aria-label="Your name" value={who} onChange={e=>setWho(e.target.value)}><option value="">Select your name…</option>{invited.map(u=><option key={u.id} value={u.id}>{u.name}</option>)}</select></label></div>
    <div className="toolbar"><Button disabled={!who} onClick={()=>api(`/api/public/availability/${id}`,{method:'POST',body:JSON.stringify({usher_id:Number(who),response:'Confirmed'})}).then(()=>setAnswer('Confirmed')).catch(()=>setNote('Could not save your response. Try again.'))}><Check/>I’m available</Button><Button variant="outline" disabled={!who} onClick={()=>api(`/api/public/availability/${id}`,{method:'POST',body:JSON.stringify({usher_id:Number(who),response:'Declined'})}).then(()=>setAnswer('Declined')).catch(()=>setNote('Could not save your response. Try again.'))}>Not available</Button></div>{note&&<p role="alert" className="text-warning mt-4">{note}</p>}</div></PublicShell>;
}

export function ClientPage({id}:{id:string}){
  const [project,setProject]=useState<PublicProject|null>(null);const [team,setTeam]=useState<Usher[]>([]);const [picked,setPicked]=useState<number[]>([]);const [done,setDone]=useState(false);const [view,setView]=useState<Usher|null>(null);const [error,setError]=useState('');const [note,setNote]=useState('');const [submitting,setSubmitting]=useState(false);
  useEffect(()=>{void api<{project:PublicProject;ushers:Usher[]}>(`/api/public/client/${id}`).then(data=>{setProject(data.project);setTeam(data.ushers)}).catch(()=>setError('This client link is not valid.'))},[id]);
  const toggle=(usherId:number)=>setPicked(p=>p.includes(usherId)?p.filter(x=>x!==usherId):[...p,usherId]);
  if(done)return <PublicShell variant="client"><div className="client-panel center"><CheckCircle2 className="big-icon"/><h1>Selection submitted</h1><p className="subtext">Thank you. Validity will finalise your team of {picked.length} ushers.</p></div></PublicShell>;
  if(error)return <PublicShell variant="client"><div className="client-panel center"><h1>Link not found</h1><p className="subtext">{error}</p></div></PublicShell>;
  if(!project)return <PublicShell variant="client"><LoadingMark label="Loading team…" /></PublicShell>;
  return <PublicShell variant="client"><div className="client-portal"><div className="client-panel"><ProjectHead project={project}/><h2 className="client-title">Select your team</h2><p className="subtext client-lead">These ushers have confirmed. Pick who you’d like on your event.</p>
    <div className="client-list">{team.length?team.map(u=>{const selected=picked.includes(u.id);return <article className={`client-card${selected?' selected':''}`} key={u.id}><div className="client-card-main"><Avatar usher={u}/><div className="client-card-copy"><h3>{u.name}</h3><p className="subtext">{u.experience} yrs · {u.events} events · {u.city}</p><div className="client-skills">{u.skills.slice(0,4).map(s=><span key={s} className="skill">{s}</span>)}</div></div><span className="rating"><Star/>{u.rating}</span></div><div className="client-card-actions"><Button type="button" variant="outline" size="sm" onClick={()=>setView(u)}>View profile</Button><Button type="button" variant={selected?'secondary':'default'} size="sm" onClick={()=>toggle(u.id)}>{selected?<><Check/>Selected</>:'Select'}</Button></div></article>}):<div className="empty">No confirmed ushers yet.</div>}</div>
    {note&&<p role="alert" className="text-warning mt-3">{note}</p>}</div>
    <div className="client-sticky"><span className="subtext">{picked.length} selected</span><Button disabled={!picked.length} pending={submitting} onClick={()=>{setSubmitting(true);void api(`/api/public/client/${id}`,{method:'POST',body:JSON.stringify({usher_ids:picked})}).then(()=>setDone(true)).catch(()=>setNote('Could not submit your selection. Try again.')).finally(()=>setSubmitting(false))}}><Check/>Submit selection</Button></div></div>
    <Dialog open={!!view} onOpenChange={()=>setView(null)}><DialogContent className="max-w-2xl">{view&&<><DialogTitle>{view.name}</DialogTitle><DialogDescription>{view.city} · {view.gender}</DialogDescription>
      <UsherPhotos usher={view}/>
      <div className="profile-detail"><div><small>Experience</small><strong>{view.experience} years · {view.events} events</strong></div><div><small>Rating</small><span className="rating"><Star/>{view.rating} / 5</span></div><div><small>Languages</small>{[...view.languages, view.otherLanguages].filter(Boolean).join(', ')||'—'}</div><div><small>Availability</small>{view.availability||'—'}</div><div><small>Education</small>{view.educationLevel||'—'}</div><div><small>Institution</small>{view.institution||'—'}</div><div><small>Field of study</small>{view.fieldOfStudy||'—'}</div><div><small>Occupation</small>{view.occupation||'—'}</div><div><small>Employer</small>{view.employer||'—'}</div><div><small>Employment</small>{view.employmentStatus||'—'}</div><div className="col-span-2"><small>Preferred events</small>{(view.preferredEvents??[]).join(', ')||'—'}</div></div>
      <h3 className="mb-1">Clothing sizes</h3>
      <div className="profile-detail"><div><small>T-shirt</small>{view.tshirtSize||'—'}</div><div><small>Shirt</small>{view.shirtSize||'—'}</div><div><small>Trouser</small>{view.trouserSize||'—'}</div><div><small>Shoe</small>{view.shoeSize||'—'}</div></div>
      <h3 className="mb-2">Skills</h3><div className="mb-4">{view.skills.length?view.skills.map(s=><span key={s} className="skill">{s}</span>):<span className="subtext">No skills listed.</span>}</div>
      <h3 className="mb-2">Event experience</h3>{(view.experiences??[]).length?(view.experiences??[]).map(item=><div key={`${item.event}-${item.role}`} className="review-row"><div className="flex-1"><strong>{item.event}</strong><div className="subtext">{[item.client, item.role, item.eventType].filter(Boolean).join(' · ')||'—'}</div></div></div>):<p className="subtext">No previous events listed.</p>}
      <Button className="w-full mt-4" onClick={()=>{setPicked(p=>p.includes(view.id)?p:[...p,view.id]);setView(null)}}><Check/>{picked.includes(view.id)?'Selected':'Select for my team'}</Button></>}</DialogContent></Dialog></PublicShell>;
}

export function RatePage({id}:{id:string}){
  const [project,setProject]=useState<PublicProject|null>(null);const [team,setTeam]=useState<Usher[]>([]);const [scores,setScores]=useState<Record<number,number>>({});const [comments,setComments]=useState<Record<number,string>>({});const [done,setDone]=useState(false);const [error,setError]=useState('');const [note,setNote]=useState('');
  useEffect(()=>{void api<{project:PublicProject;ushers:Usher[]}>(`/api/public/ratings/${id}`).then(data=>{setProject(data.project);setTeam(data.ushers)}).catch(()=>setError('This rating link is not valid.'))},[id]);
  if(done)return <PublicShell><div className="public-card center"><CheckCircle2 className="big-icon"/><h1>Thank you for your feedback</h1><p className="subtext">Your ratings help us build even better teams.</p></div></PublicShell>;
  if(error)return <PublicShell><div className="public-card center"><h1>Link not found</h1><p className="subtext">{error}</p></div></PublicShell>;
  if(!project)return <PublicShell><LoadingMark label="Loading team…" /></PublicShell>;
  return <PublicShell><div className="public-card"><ProjectHead project={project}/><h2 className="mb-1">Rate your event team</h2><p className="subtext mb-4">How did each usher perform?</p>
    {team.map(u=><div className="review-row" key={u.id}><Avatar usher={u}/><div className="flex-1"><h3>{u.name}</h3><textarea className="w-full mt-2" rows={2} placeholder="Optional comment" value={comments[u.id]??''} onChange={e=>setComments(c=>({...c,[u.id]:e.target.value}))}/></div><div className="stars" role="radiogroup" aria-label={`Rate ${u.name}`}>{[1,2,3,4,5].map(n=><button type="button" key={n} aria-label={`${n} stars`} className={(scores[u.id]||0)>=n?'on':''} onClick={()=>setScores(s=>({...s,[u.id]:n}))}><Star/></button>)}</div></div>)}
    <Button className="w-full mt-5" disabled={!team.length||Object.keys(scores).length<team.length} onClick={()=>api(`/api/public/ratings/${id}`,{method:'POST',body:JSON.stringify({ratings:team.map(u=>({usher_id:u.id,score:scores[u.id],comment:comments[u.id]??''}))})}).then(()=>setDone(true)).catch(()=>setNote('Could not submit your ratings. Try again.'))}><Check/>Submit ratings</Button>{note&&<p role="alert" className="text-warning mt-4">{note}</p>}</div></PublicShell>;
}

export function IndividualRespondPage({token}:{token:string}){
  const [project,setProject]=useState<PublicProject|null>(null);const [usher,setUsher]=useState<Named|null>(null);const [answer,setAnswer]=useState('');const [error,setError]=useState('');const [note,setNote]=useState('');
  useEffect(()=>{void api<{project:PublicProject;usher:Named}>(`/api/public/confirm/${token}`).then(data=>{setProject(data.project);setUsher(data.usher)}).catch(()=>setError('This confirmation link is not valid.'))},[token]);
  if(answer)return <PublicShell><div className="public-card center"><CheckCircle2 className="big-icon"/><h1>{answer==='Confirmed'?'You’re confirmed!':'Thanks for letting us know'}</h1><p className="subtext">{answer==='Confirmed'?'We’ll share the call time and final details closer to the event.':'We hope to work with you on the next event.'}</p></div></PublicShell>;
  if(error)return <PublicShell><div className="public-card center"><h1>Link not found</h1><p className="subtext">{error}</p></div></PublicShell>;
  if(!project||!usher)return <PublicShell><LoadingMark label="Loading invitation…" /></PublicShell>;
  return <PublicShell><div className="public-card"><ProjectHead project={project}/><h2 className="mb-3">Confirm your availability</h2><p className="subtext mb-4">Hi {usher.name}. This link is only for you.</p><EventFacts project={project}/>
    <div className="toolbar"><Button onClick={()=>api(`/api/public/confirm/${token}`,{method:'POST',body:JSON.stringify({response:'Confirmed'})}).then(()=>setAnswer('Confirmed')).catch(()=>setNote('Could not save your response. Try again.'))}><Check/>I’m available</Button><Button variant="outline" onClick={()=>api(`/api/public/confirm/${token}`,{method:'POST',body:JSON.stringify({response:'Declined'})}).then(()=>setAnswer('Declined')).catch(()=>setNote('Could not save your response. Try again.'))}>Not available</Button></div>{note&&<p role="alert" className="text-warning mt-4">{note}</p>}</div></PublicShell>;
}

export function LoginPage(){
  const [email,setEmail]=useState('');const [password,setPassword]=useState('');const [error,setError]=useState('');const [pending,setPending]=useState(false);
  return <PublicShell><form className="public-card narrow" onSubmit={e=>{e.preventDefault();setError('');setPending(true);void login(email,password).then(()=>{window.location.assign('/')}).catch(()=>{setError('Those credentials were not recognized.');setPending(false)})}}>
    <h1>Sign in</h1><p className="subtext mb-6">Validity administrators</p>
    {error&&<p role="alert" className="text-warning mb-4">{error}</p>}
    <div className="form-grid"><label className="full">Email<input type="email" required autoComplete="username" value={email} onChange={e=>setEmail(e.target.value)} placeholder="admin@validity.et"/></label><label className="full">Password<input type="password" required autoComplete="current-password" value={password} onChange={e=>setPassword(e.target.value)}/></label><Button type="submit" className="full" pending={pending}>Sign in</Button></div>
  </form></PublicShell>;
}
