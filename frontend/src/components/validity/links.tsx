import {useEffect,useRef,useState,type FormEvent,type HTMLAttributes,type ReactNode} from 'react';
import {Copy,Check,Send,MessageCircle,Mail,Link2,Star,CheckCircle2,CalendarDays,MapPin,Instagram,Music2,Users} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Dialog,DialogContent,DialogTitle,DialogDescription} from '@/components/ui/dialog';
import {api,apiFieldErrorMap,firstApiField,formatApiError,login} from '@/lib/api';
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
function formatLocalDate(d:Date){return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`}
function adultDobMax(){const d=new Date();d.setHours(0,0,0,0);d.setFullYear(d.getFullYear()-18);return formatLocalDate(d)}
function isAdultDob(value:string){return Boolean(value)&&value<=adultDobMax()}
const MAX_IMAGE_BYTES=2*1024*1024;
const ALLOWED_IMAGE_TYPES=new Set(['image/jpeg','image/jpg','image/png','image/webp']);
function imageProblem(file:File):string|null{
  const type=(file.type||'').toLowerCase();
  const name=file.name.toLowerCase();
  if(type.includes('heic')||type.includes('heif')||name.endsWith('.heic')||name.endsWith('.heif')){
    return 'HEIC photos from iPhone are not accepted. Save or export the image as JPEG, then try again.';
  }
  const extOk=/\.(jpe?g|png|webp)$/i.test(file.name);
  const typeOk=!type||ALLOWED_IMAGE_TYPES.has(type);
  if(!typeOk&&!extOk)return 'That file is not a JPEG, PNG, or WebP photo.';
  if(type.startsWith('image/')&&!ALLOWED_IMAGE_TYPES.has(type)&&!extOk)return 'Use a JPEG, PNG, or WebP photo.';
  return null;
}
async function compressImage(file:File,maxBytes=MAX_IMAGE_BYTES):Promise<File>{
  if(file.size<=maxBytes)return file;
  let bitmap:ImageBitmap;
  try{bitmap=await createImageBitmap(file);}catch{throw new Error('IMAGE_UNREADABLE');}
  const canvas=document.createElement('canvas');
  const ctx=canvas.getContext('2d');
  if(!ctx){bitmap.close();throw new Error('IMAGE_TOO_LARGE');}
  let width=bitmap.width;let height=bitmap.height;let quality=0.86;
  for(let attempt=0;attempt<10;attempt++){
    canvas.width=Math.max(1,width);canvas.height=Math.max(1,height);
    ctx.clearRect(0,0,canvas.width,canvas.height);
    ctx.drawImage(bitmap,0,0,canvas.width,canvas.height);
    const blob=await new Promise<Blob|null>(resolve=>canvas.toBlob(resolve,'image/jpeg',quality));
    if(blob&&blob.size<=maxBytes){
      bitmap.close();
      const base=file.name.replace(/\.[^.]+$/,'')||'photo';
      return new File([blob],`${base}.jpg`,{type:'image/jpeg',lastModified:Date.now()});
    }
    if(quality>0.55)quality-=0.08;
    else{width=Math.round(width*0.82);height=Math.round(height*0.82);quality=0.78;}
    if(width<240||height<240)break;
  }
  bitmap.close();
  throw new Error('IMAGE_TOO_LARGE');
}
function photoPrepareError(err:unknown):string{
  const code=err instanceof Error?err.message:'';
  if(code==='IMAGE_TOO_LARGE')return 'Each photo must be 2 MB or smaller. Try a smaller image.';
  if(code==='IMAGE_UNREADABLE')return 'That image could not be read. Use a JPEG, PNG, or WebP photo.';
  if(err instanceof Error&&err.message)return err.message;
  return 'That image could not be used. Try a JPEG, PNG, or WebP photo under 2 MB.';
}
async function preparePhoto(file:File):Promise<File>{
  const problem=imageProblem(file);
  if(problem)throw new Error(problem);
  return compressImage(file);
}
const PHONE_PATTERN='^[0-9+ ]{9,20}$';
const CITIES=['Addis Ababa','Adama','Bahir Dar','Hawassa','Mekelle','Dire Dawa','Other'];
const SOCIAL_LINKS=[
  {label:'Telegram',href:'https://t.me/validity_events',icon:Send},
  {label:'Instagram',href:'https://www.instagram.com/validity_events/',icon:Instagram},
  {label:'TikTok',href:'https://www.tiktok.com/@validity_events',icon:Music2},
  {label:'Usher Telegram group',href:'https://t.me/validity_ushers',icon:Users},
] as const;
function cityChoices(current?:string|null){
  const value=(current??'').trim();
  if(!value||CITIES.includes(value))return CITIES;
  return [...CITIES.filter(city=>city!=='Other'),value,'Other'];
}
function SuccessCard({title,copy}:{title:string;copy:string}){
  return <div className="reg-success">
    <CheckCircle2 className="big-icon"/>
    <h1>{title}</h1>
    <p className="subtext">{copy}</p>
    <div className="reg-socials">
      <h2>Join our socials</h2>
      <p className="subtext">Stay in the loop with Validity updates, call-times, and the usher community.</p>
      <div className="reg-social-grid">{SOCIAL_LINKS.map(link=>{const Icon=link.icon;return <Button key={link.href} asChild variant={link.href.includes('validity_ushers')?'default':'outline'}><a href={link.href} target="_blank" rel="noreferrer"><Icon/>{link.label}</a></Button>;})}</div>
    </div>
  </div>;
}
function F({label,name,type='text',required,full,options,placeholder,max,min,minLength,maxLength,title,pattern,inputMode,autoComplete,onChange,defaultValue,hint,error}:{label:string;name:string;type?:string;required?:boolean;full?:boolean;options?:string[];placeholder?:string;max?:string;min?:string;minLength?:number;maxLength?:number;title?:string;pattern?:string;inputMode?:HTMLAttributes<HTMLInputElement>['inputMode'];autoComplete?:string;onChange?:HTMLAttributes<HTMLInputElement>['onChange'];defaultValue?:string|number;hint?:string;error?:string}){
  const mark=(event:FormEvent<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>)=>{event.currentTarget.classList.remove('field-invalid');event.currentTarget.setCustomValidity('');onChange?.(event as Parameters<NonNullable<typeof onChange>>[0]);};
  return <label className={`reg-field${full?' full':''}${error?' has-error':''}`}>{label}{required?<span className="text-primary"> *</span>:<span className="reg-optional"> Optional</span>}{options?<select name={name} required={required} defaultValue={defaultValue??''} aria-invalid={Boolean(error)} className={error?'field-invalid':''} onChange={mark as HTMLAttributes<HTMLSelectElement>['onChange']}><option value="" disabled={!defaultValue}>Select…</option>{options.map(o=><option key={o}>{o}</option>)}</select>:type==='textarea'?<textarea name={name} rows={3} required={required} placeholder={placeholder} maxLength={maxLength} defaultValue={defaultValue} aria-invalid={Boolean(error)} className={error?'field-invalid':''} onChange={mark as HTMLAttributes<HTMLTextAreaElement>['onChange']}/>:<input name={name} type={type} required={required} placeholder={placeholder} max={max} min={min} minLength={minLength} maxLength={maxLength} title={title} pattern={pattern} inputMode={inputMode} autoComplete={autoComplete} aria-invalid={Boolean(error)} className={error?'field-invalid':''} onChange={mark} defaultValue={defaultValue}/>}{error?<span className="field-error" role="alert">{error}</span>:hint?<span className="subtext">{hint}</span>:null}</label>;
}
function Checks({label,name,items,selected}:{label:string;name:string;items:string[];selected?:string[]}){return <div className="full"><div className="check-label">{label}</div><div className="check-grid">{items.map(i=><label key={i} className="check-item"><input type="checkbox" name={`${name}[]`} value={i} defaultChecked={selected?.includes(i)}/>{i}</label>)}</div></div>}
function AddMore({label,children}:{label:string;children:ReactNode}){const [open,setOpen]=useState(false);return <div className="reg-add-more full"><button type="button" className="reg-add-more-toggle" aria-expanded={open} onClick={()=>setOpen(v=>!v)}>{open?'Hide':'Add'} — {label}</button><div className="reg-add-more-body form-grid" hidden={!open}>{children}</div></div>}
function PhotoPicker({files,onChange,required,onBusy,onReject,error}:{files:File[];onChange:(files:File[])=>void;required?:boolean;onBusy?:(busy:boolean)=>void;onReject?:(message:string)=>void;error?:string}){
  const inputRef=useRef<HTMLInputElement>(null);
  const [note,setNote]=useState('');const [fieldError,setFieldError]=useState('');const [busy,setBusy]=useState(false);const [preview,setPreview]=useState<string|null>(null);
  const [thumbs,setThumbs]=useState<string[]>([]);
  useEffect(()=>{const urls=files.map(file=>URL.createObjectURL(file));setThumbs(urls);return()=>{urls.forEach(url=>URL.revokeObjectURL(url));};},[files]);
  const setWorking=(value:boolean)=>{setBusy(value);onBusy?.(value);};
  const fail=(message:string)=>{setFieldError(message);onReject?.(message);};
  return <div className="full reg-photos">
    <input ref={inputRef} className="reg-file-input" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple required={required&&files.length===0} disabled={busy||files.length>=3} onChange={e=>{
      const input=e.currentTarget;const picked=Array.from(input.files??[]);
      const room=Math.max(0,3-files.length);
      setNote(picked.length>room?'Only 3 photos can be attached. Extra files were skipped.':'');
      setFieldError('');
      if(picked.length===0||room===0){input.value='';return;}
      setWorking(true);
      void (async()=>{
        try{
          const prepared:File[]=[];
          for(const file of picked.slice(0,room))prepared.push(await preparePhoto(file));
          onChange([...files,...prepared].slice(0,3));
          onReject?.('');
        }catch(err){fail(photoPrepareError(err));}
        finally{setWorking(false);input.value='';}
      })();
    }}/>
    <div className={`reg-dropzone${(fieldError||error)?' field-invalid':''}`}>
      <p>JPEG, PNG, or WebP. First photo is your profile picture. iPhone HEIC is not accepted — export as JPEG first.</p>
      <Button type="button" variant="outline" disabled={busy||files.length>=3} onClick={()=>inputRef.current?.click()}>{files.length===0?'Add photos':'Add another photo'}</Button>
    </div>
    {busy&&<p className="subtext">Preparing photos… Stay on this page until this finishes.</p>}
    {thumbs.length>0&&<ul className="reg-thumbs">{thumbs.map((src,index)=><li key={`${files[index]?.name}-${index}`}><button type="button" className="reg-thumb" onClick={()=>setPreview(src)}><img src={src} alt={index===0?'Profile photo preview':'Additional photo preview'}/><span>{index===0?'Profile':'Extra'}</span></button><Button type="button" variant="outline" size="sm" onClick={()=>onChange(files.filter((_,i)=>i!==index))}>Remove</Button></li>)}</ul>}
    {note&&!(fieldError||error)&&<p className="subtext">{note}</p>}
    {(fieldError||error)&&<p className="field-error" role="alert">{fieldError||error}</p>}
    <Dialog open={Boolean(preview)} onOpenChange={open=>{if(!open)setPreview(null)}}><DialogContent><DialogTitle>Photo preview</DialogTitle><DialogDescription>Check that the photo is clear before you continue.</DialogDescription>{preview&&<img className="reg-preview-img" src={preview} alt="Selected photo"/>}</DialogContent></Dialog>
  </div>;
}
function IdPhotoPicker({file,onChange,label,required=true,hint,onBusy,onReject,error}:{file:File|null;onChange:(file:File|null)=>void;label:string;required?:boolean;hint?:string;onBusy?:(busy:boolean)=>void;onReject?:(message:string)=>void;error?:string}){
  const inputRef=useRef<HTMLInputElement>(null);
  const [fieldError,setFieldError]=useState('');const [busy,setBusy]=useState(false);const [preview,setPreview]=useState(false);
  const [thumb,setThumb]=useState('');
  useEffect(()=>{if(!file){setThumb('');return;}const url=URL.createObjectURL(file);setThumb(url);return()=>URL.revokeObjectURL(url);},[file]);
  const setWorking=(value:boolean)=>{setBusy(value);onBusy?.(value);};
  const fail=(message:string)=>{setFieldError(message);onReject?.(message);};
  return <div className="full reg-photos">
    <input ref={inputRef} className="reg-file-input" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required={required&&!file} disabled={busy} onChange={e=>{
      const input=e.currentTarget;const picked=input.files?.[0]??null;
      setFieldError('');
      if(!picked){input.value='';return;}
      setWorking(true);
      void preparePhoto(picked).then(next=>{onChange(next);setFieldError('');onReject?.('');}).catch(err=>{onChange(null);fail(photoPrepareError(err));}).finally(()=>{setWorking(false);input.value='';});
    }}/>
    <div className={`reg-dropzone${(fieldError||error)?' field-invalid':''}`}>
      <strong>{label}{required&&<span className="text-primary"> *</span>}</strong>
      <p>{hint??'JPEG, PNG, or WebP, max 2 MB'}</p>
      <Button type="button" variant="outline" disabled={busy} onClick={()=>inputRef.current?.click()}>{file?'Replace photo':'Upload photo'}</Button>
    </div>
    {busy&&<p className="subtext">Preparing {label.toLowerCase()}… Stay on this page until this finishes.</p>}
    {thumb&&<div className="reg-id-preview"><button type="button" className="reg-thumb" onClick={()=>setPreview(true)}><img src={thumb} alt={`${label} preview`}/></button><Button type="button" variant="outline" size="sm" onClick={()=>onChange(null)}>Remove</Button></div>}
    {(fieldError||error)&&<p className="field-error" role="alert">{fieldError||error}</p>}
    <Dialog open={preview} onOpenChange={setPreview}><DialogContent><DialogTitle>{label}</DialogTitle><DialogDescription>Make sure the ID is readable.</DialogDescription>{thumb&&<img className="reg-preview-img" src={thumb} alt={label}/>}</DialogContent></Dialog>
  </div>;
}

function withoutEmptyFiles(body:FormData){
  for(const [key,value] of Array.from(body.entries())){
    if(value instanceof File && (value.size===0 || value.name===''))body.delete(key);
  }
  return body;
}
const fieldLabels:Record<string,string>={name:'Full name',phone:'Phone number',gender:'Gender',dob:'Date of birth',city:'City',cityOther:'City or town',address:'Address',email:'Email',telegram:'Telegram username',ecName:'Emergency contact name',ecRel:'Emergency contact relationship',ecPhone:'Emergency contact phone',holder:'Account holder name',acct:'CBE account number',telebirr:'Telebirr number',idType:'ID type',idNo:'ID / passport number',idPhoto:'ID photo',idPhotoFront:'ID front photo',idPhotoBack:'ID back photo',photo1:'Profile photo',photo2:'Additional photo',photo3:'Additional photo',ig:'Instagram',fb:'Facebook',li:'LinkedIn',tt:'TikTok',confirmAccuracy:'Accuracy confirmation',years:'Years of event experience',events:'Number of events worked'};
const fieldStep:(field:string)=>string=(field)=>{
  if(['name','phone','gender','dob','city','cityOther','address','email','telegram'].includes(field))return 'About you';
  if(['ecName','ecRel','ecPhone'].includes(field))return 'Emergency';
  if(['edu','inst','field','occ','employer','empStatus'].includes(field))return 'Your work profile';
  if(['languages','otherLang','skills','prefs','availability','years','events'].includes(field)||field.startsWith('languages')||field.startsWith('skills')||field.startsWith('prefs')||field.startsWith('ex'))return 'Your work profile';
  if(field.startsWith('photo')||['tshirt','shirt','trouser','shoe'].includes(field))return 'Photos & sizes';
  if(['bank','holder','acct','telebirr','idType','idNo','idPhoto','idPhotoFront','idPhotoBack'].includes(field))return 'Payment & ID';
  if(['ig','fb','li','tt','confirmAccuracy'].includes(field))return 'Review';
  return 'Review';
};
const fieldSection:(field:string)=>string=(field)=>{
  if(['name','phone','gender','dob','city','cityOther','address','email','telegram'].includes(field))return 'about';
  if(['ecName','ecRel','ecPhone'].includes(field))return 'emergency';
  if(['edu','inst','field','occ','employer','empStatus'].includes(field))return 'background';
  if(['languages','otherLang','skills','prefs','availability','years','events'].includes(field)||field.startsWith('languages')||field.startsWith('skills')||field.startsWith('prefs')||field.startsWith('ex'))return 'skills';
  if(field.startsWith('photo')||['tshirt','shirt','trouser','shoe'].includes(field))return 'photos';
  if(['bank','holder','acct','telebirr','idType','idNo','idPhoto','idPhotoFront','idPhotoBack'].includes(field))return 'payment';
  if(['ig','fb','li','tt'].includes(field))return 'social';
  return '';
};
function fieldMessage(field:HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement){
  const label=fieldLabels[field.name]||field.name||'This field';
  if(field.validity.valueMissing)return `${label} is required to continue.`;
  if(field.validity.typeMismatch)return field.type==='email'?`Enter a valid email address.`:`Enter a valid ${label.toLowerCase()}.`;
  if(field.validity.patternMismatch&&field.name==='acct')return 'CBE account numbers must start with 1000 and contain digits only.';
  if(field.validity.patternMismatch&&(field.name==='phone'||field.name==='ecPhone'||field.name==='telebirr'))return 'Enter a phone number like 09xxxxxxxx or +251 9xxxxxxxx.';
  if(field.validity.patternMismatch)return `${label} is not in the correct format.`;
  if(field.name==='dob'&&(field.validity.rangeOverflow||field.validity.rangeUnderflow))return 'Ushers must be 18 or older.';
  if(field.validity.rangeOverflow||field.validity.rangeUnderflow)return `${label} is out of range.`;
  if(field.validity.tooShort&&field.name==='name')return 'Full name must be at least 3 characters.';
  if(field.validity.tooShort)return `${label} is too short.`;
  if(field.validity.tooLong)return `${label} is too long.`;
  if(field.validity.badInput)return `Enter a valid value for ${label.toLowerCase()}.`;
  return field.validationMessage||`Please check ${label.toLowerCase()}.`;
}

function formValue(form:HTMLFormElement|null,name:string){const field=form?.elements.namedItem(name);if(field instanceof HTMLInputElement||field instanceof HTMLSelectElement||field instanceof HTMLTextAreaElement)return field.value;return '';}

export function RegistrationPage({token}:{token:string}){
  const formRef=useRef<HTMLFormElement>(null);
  const errorRef=useRef<HTMLParagraphElement>(null);
  const [done,setDone]=useState(false);const [step,setStep]=useState(0);const [exp,setExp]=useState(1);const [photos,setPhotos]=useState<File[]>([]);const [idPhotoFront,setIdPhotoFront]=useState<File|null>(null);const [idPhotoBack,setIdPhotoBack]=useState<File|null>(null);const [error,setError]=useState('');const [fieldErrors,setFieldErrors]=useState<Record<string,string>>({});const [submitting,setSubmitting]=useState(false);const [photoJobs,setPhotoJobs]=useState(0);const [city,setCity]=useState('');const [linkError,setLinkError]=useState('');const [linkReady,setLinkReady]=useState(false);
  const photoBusy=photoJobs>0;
  const markPhotoBusy=(busy:boolean)=>setPhotoJobs(n=>Math.max(0,n+(busy?1:-1)));
  useEffect(()=>{void api(`/api/public/register/${token}`).then(()=>setLinkReady(true)).catch(()=>setLinkError('This registration link is not valid or has been revoked.'));},[token]);
  const steps:[string,ReactNode][]=[
    ['About you',<Section title="About you"><F label="Full name" name="name" required minLength={3} maxLength={255} autoComplete="name" error={fieldErrors.name}/><F label="Phone number" name="phone" type="tel" required inputMode="tel" autoComplete="tel" pattern={PHONE_PATTERN} maxLength={50} placeholder="09xx xxx xxx or +251 9xx xxx xxx" hint="Use your Ethiopian mobile number." error={fieldErrors.phone}/><F label="Gender" name="gender" required options={['Female','Male']} error={fieldErrors.gender}/><F label="Date of birth" name="dob" type="date" required max={adultDobMax()} title="You must be 18 or older" autoComplete="bday" error={fieldErrors.dob} onChange={e=>{const el=e.currentTarget;if(el.value&&!isAdultDob(el.value))el.setCustomValidity('Ushers must be 18 or older.');else el.setCustomValidity('');}}/><p className="full subtext">Ushers must be 18 or older.</p><F label="City" name="city" required options={CITIES} error={fieldErrors.city} onChange={e=>setCity(e.currentTarget.value)}/>{city==='Other'&&<F label="City or town" name="cityOther" required full maxLength={100} hint="Write the city if it is not in the list." error={fieldErrors.cityOther}/>}<F label="Address" name="address" required full maxLength={255} autoComplete="street-address" error={fieldErrors.address}/><F label="Email" name="email" type="email" autoComplete="email" maxLength={255} error={fieldErrors.email}/><F label="Telegram username" name="telegram" placeholder="@username" maxLength={100} error={fieldErrors.telegram}/></Section>],
    ['Emergency',<Section title="Emergency contact"><F label="Contact name" name="ecName" required full maxLength={255} autoComplete="off" error={fieldErrors.ecName}/><F label="Relationship" name="ecRel" required maxLength={100} error={fieldErrors.ecRel}/><F label="Phone number" name="ecPhone" type="tel" required inputMode="tel" pattern={PHONE_PATTERN} maxLength={50} placeholder="09xx xxx xxx" error={fieldErrors.ecPhone}/></Section>],
    ['Your work profile',<><p className="full subtext">Optional — skip if you want to finish faster. You can add this later if Validity asks.</p><Section title="Education"><F label="Highest education level" name="edu" options={['High school','Diploma','Bachelor’s degree','Master’s degree','Other']}/><F label="Institution" name="inst"/><F label="Field of study" name="field" full/></Section><Section title="Employment"><F label="Current occupation" name="occ"/><F label="Employer / company" name="employer"/><F label="Employment status" name="empStatus" full options={['Student','Employed','Self-employed','Unemployed','Freelancer']}/></Section><Section title="Languages & skills"><Checks label="Languages you speak" name="languages" items={['Amharic','English','Afaan Oromo','Tigrinya','Somali','Arabic','Other']}/><F label="Other languages" name="otherLang" full/><Checks label="Skills" name="skills" items={['Registration','Guest Relations','VIP Handling','Crowd Management','Hospitality','Brand Ambassador','Ticketing','Check-in','Event Protocol','Public Speaking','Customer Service','Team Leader','Supervisor','Technical Support']}/><Checks label="Preferred event types" name="prefs" items={['Corporate','Conference','Exhibition','Concert','Festival','Product Launch','Brand Activation','Wedding','VIP','Government','Sports','Retail/BTL']}/><F label="General availability" name="availability" full options={['Weekdays','Weekends','Weekdays & weekends','Evenings only']}/></Section><Section title="Event experience"><F label="Years of event experience" name="years" type="number" min="0"/><F label="Number of events worked" name="events" type="number" min="0"/>
      {Array.from({length:exp}).map((_,i)=><div key={i} className="full sub-entry"><strong>Experience {i+1}</strong><div className="form-grid"><F label="Event name" name={`exEvent${i}`}/><F label="Client" name={`exClient${i}`}/><F label="Role performed" name={`exRole${i}`}/><F label="Event type" name={`exType${i}`} options={['Corporate Event','Conference','Exhibition','Concert','Wedding','Product Launch','Brand Activation','VIP Event']}/></div>{exp>1&&<Button type="button" variant="outline" size="sm" onClick={()=>setExp(n=>Math.max(1,n-1))}>Remove last</Button>}</div>)}
      <div className="full"><Button type="button" variant="outline" size="sm" onClick={()=>setExp(n=>n+1)}>+ Add experience</Button></div></Section></>],
    ['Photos & sizes',<><Section title="Photos"><PhotoPicker files={photos} onChange={setPhotos} required error={fieldErrors.photo1} onBusy={markPhotoBusy} onReject={message=>{setError(message);setFieldErrors(message?{photo1:message}:{})}}/><F label="T-shirt size" name="tshirt" options={['XS','S','M','L','XL','XXL']}/><p className="full subtext">Optional clothing sizes help Validity assign uniforms.</p><F label="Shirt size" name="shirt" options={['XS','S','M','L','XL','XXL']}/><F label="Trouser size" name="trouser" maxLength={20}/><F label="Shoe size" name="shoe" maxLength={20}/></Section></>],
    ['Payment & ID',<><Section title="Payment details (private)"><p className="full subtext">Only Validity administrators can see this. It is never shared with clients.</p><div className="full"><small className="text-muted-foreground">Bank</small><strong>CBE</strong><input type="hidden" name="bank" value="CBE"/></div><F label="Account holder name" name="holder" required full maxLength={255} error={fieldErrors.holder}/><F label="CBE account number" name="acct" required full placeholder="1000xxxxxxxxx" pattern="1000[0-9]+" inputMode="numeric" maxLength={100} title="Must start with 1000 and contain digits only" hint="Usually 13 digits, starting with 1000." error={fieldErrors.acct}/><F label="Telebirr number" name="telebirr" type="tel" full placeholder="Usually your phone number" inputMode="tel" pattern={PHONE_PATTERN} maxLength={50} error={fieldErrors.telebirr}/></Section><Section title="Identification (private)"><p className="full subtext">Kept private and used only to verify your profile. Clients never see this. Upload both sides of your ID. For a passport, photograph the data page and the closed cover.</p><F label="ID type" name="idType" required options={['Kebele ID','National ID (Fayda)','Passport','Driver’s licence']} full error={fieldErrors.idType}/><F label="ID / passport number" name="idNo" full maxLength={100} error={fieldErrors.idNo}/><div className="id-photo-row full"><IdPhotoPicker label="ID front" file={idPhotoFront} error={fieldErrors.idPhotoFront} onChange={setIdPhotoFront} hint="Front of your ID (JPEG, PNG, or WebP)" onBusy={markPhotoBusy} onReject={message=>{setError(message);setFieldErrors(current=>({...current,idPhotoFront:message}));}}/><IdPhotoPicker label="ID back" file={idPhotoBack} error={fieldErrors.idPhotoBack} onChange={setIdPhotoBack} hint="Back of your ID (JPEG, PNG, or WebP)" onBusy={markPhotoBusy} onReject={message=>{setError(message);setFieldErrors(current=>({...current,idPhotoBack:message}));}}/></div></Section></>],
    ['Review',<div className="reg-confirm"><div className="reg-recap"><div><small>Name</small><strong>{formValue(formRef.current,'name')||'—'}</strong></div><div><small>Phone</small><strong>{formValue(formRef.current,'phone')||'—'}</strong></div><div><small>City</small><strong>{(formValue(formRef.current,'city')==='Other'?formValue(formRef.current,'cityOther'):formValue(formRef.current,'city'))||'—'}</strong></div><div><small>Emergency</small><strong>{formValue(formRef.current,'ecName')||'—'}</strong></div><div><small>Photos</small><strong>{photos.length} attached</strong></div><div><small>CBE</small><strong>{formValue(formRef.current,'acct')?`••••${formValue(formRef.current,'acct').slice(-4)}`:'—'}</strong></div><div><small>ID</small><strong>{formValue(formRef.current,'idType')||'—'}</strong></div></div><label className={`check-item${fieldErrors.confirmAccuracy?' field-invalid':''}`}><input type="checkbox" name="confirmAccuracy" required/>I confirm the information above is accurate.</label>{fieldErrors.confirmAccuracy&&<p className="field-error" role="alert">{fieldErrors.confirmAccuracy}</p>}<AddMore label="social links"><F label="Instagram" name="ig" maxLength={2048} error={fieldErrors.ig}/><F label="Facebook" name="fb" maxLength={2048} error={fieldErrors.fb}/><F label="LinkedIn" name="li" maxLength={2048} error={fieldErrors.li}/><F label="TikTok" name="tt" maxLength={2048} error={fieldErrors.tt}/></AddMore></div>],
  ];
  const showError=(message:string)=>{setError(message);requestAnimationFrame(()=>errorRef.current?.scrollIntoView({behavior:'smooth',block:'nearest'}));};
  const failField=(field:HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement,message:string)=>{
    field.classList.add('field-invalid');
    setFieldErrors({[field.name]:message});
    showError(message);
    try{field.focus({preventScroll:false});}catch{/* ignore */}
  };
  const showStep=(index:number,clearError=true)=>{setStep(index);if(clearError){setError('');setFieldErrors({});}const body=formRef.current?.querySelector('.reg-body');if(body)body.scrollTop=0;};
  const paneValid=(index:number)=>{
    if(photoBusy){showError('Wait for your photos to finish preparing before continuing.');return false;}
    if(steps[index]?.[0]==='Photos & sizes'){
      if(photos.length===0){setFieldErrors({photo1:'Add at least one profile photo to continue.'});showError('Add at least one profile photo to continue.');return false;}
      if(photos.some(file=>file.size>MAX_IMAGE_BYTES)){setFieldErrors({photo1:'Each photo must be 2 MB or smaller.'});showError('Each photo must be 2 MB or smaller.');return false;}
    }
    const pane=formRef.current?.querySelectorAll<HTMLElement>('[data-step]')[index];
    if(!pane)return true;
    const wasHidden=pane.hidden;
    pane.hidden=false;
    pane.querySelectorAll('.field-invalid').forEach(node=>node.classList.remove('field-invalid'));
    const dob=pane.querySelector<HTMLInputElement>('input[name=dob]');
    if(dob){
      if(dob.value&&!isAdultDob(dob.value))dob.setCustomValidity('Ushers must be 18 or older.');
      else dob.setCustomValidity('');
    }
    const fields=pane.querySelectorAll<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>('input,select,textarea');
    for(const field of fields){
      if(field instanceof HTMLInputElement&&field.type==='file')continue;
      if(field instanceof HTMLInputElement&&field.type==='hidden')continue;
      if(field.checkValidity())continue;
      const message=field.name==='confirmAccuracy'?'Please confirm your details are accurate to submit.':field.name==='dob'&&!isAdultDob((field as HTMLInputElement).value)?'Ushers must be 18 or older.':fieldMessage(field);
      failField(field,message);
      pane.hidden=wasHidden;
      return false;
    }
    if(steps[index]?.[0]==='About you'&&dob&&!isAdultDob(dob.value)){
      dob.setCustomValidity('Ushers must be 18 or older.');
      failField(dob,'Ushers must be 18 or older.');
      pane.hidden=wasHidden;
      return false;
    }
    if(steps[index]?.[0]==='Payment & ID'){
      if(!idPhotoFront){setFieldErrors({idPhotoFront:'Attach a clear photo of the front of your ID to continue.'});showError('Attach a clear photo of the front of your ID to continue.');pane.hidden=wasHidden;return false;}
      if(!idPhotoBack){setFieldErrors({idPhotoBack:'Attach a clear photo of the back of your ID to continue.'});showError('Attach a clear photo of the back of your ID to continue.');pane.hidden=wasHidden;return false;}
      if(idPhotoFront.size>MAX_IMAGE_BYTES||idPhotoBack.size>MAX_IMAGE_BYTES){setFieldErrors({idPhotoFront:'Each ID photo must be 2 MB or smaller.'});showError('Each ID photo must be 2 MB or smaller.');pane.hidden=wasHidden;return false;}
    }
    pane.hidden=wasHidden;
    setError('');
    setFieldErrors({});
    return true;
  };
  const submitRegistration=async(form:HTMLFormElement)=>{
    for(let index=0;index<steps.length;index++){
      if(!paneValid(index)){showStep(index,false);return;}
    }
    setError('');setSubmitting(true);
    const body=withoutEmptyFiles(new FormData(form));
    if(body.get('city')==='Other'){
      const other=String(body.get('cityOther')??'').trim();
      if(other)body.set('city',other);
    }
    body.delete('cityOther');
    const account=String(body.get('acct')??'').replace(/\D/g,'');
    if(account)body.set('acct',account);
    body.delete('confirmAccuracy');
    (['photo1','photo2','photo3','idPhoto','idPhotoFront','idPhotoBack'] as const).forEach(key=>body.delete(key));
    photos.slice(0,3).forEach((file,index)=>body.append(`photo${index+1}`,file));
    body.append('idPhotoFront',idPhotoFront);
    body.append('idPhotoBack',idPhotoBack);
    try{
      await api(`/api/public/register/${token}`,{method:'POST',body});
      setDone(true);window.scrollTo(0,0);
    }catch(err){
      const mapped=apiFieldErrorMap(err);
      const field=Object.keys(mapped)[0];
      const message=mapped[field??'']||formatApiError(err);
      setFieldErrors(mapped);
      showError(message);
      if(field){
        const target=steps.findIndex(([title])=>title===fieldStep(field.split('.')[0]??field));
        if(target>=0)showStep(target,false);
      }
    }finally{
      setSubmitting(false);
    }
  };
  if(done)return <PublicShell variant="reg"><SuccessCard title="Registration received" copy="Thank you! The Validity team will review your profile and contact you once it’s verified."/></PublicShell>;
  if(linkError)return <PublicShell><div className="public-card center"><h1>Link not found</h1><p className="subtext">{linkError}</p></div></PublicShell>;
  if(!linkReady)return <PublicShell><LoadingMark label="Checking registration link…" /></PublicShell>;
  const optional=steps[step]?.[0]==='Your work profile';
  return <PublicShell variant="reg"><form ref={formRef} className="reg-form" noValidate onSubmit={e=>{e.preventDefault();if(submitting)return;if(step<steps.length-1){if(paneValid(step))showStep(step+1);return;}if(!paneValid(step))return;void submitRegistration(e.currentTarget);}}>
    <div className="reg-top"><img src="/validity-events-logo.svg" alt="Validity Events" className="reg-logo"/><p className="reg-tagline">Join the usher community · about 3 minutes</p>{error&&<p ref={errorRef} role="alert" aria-live="assertive" className="reg-error">{error}</p>}<div className="reg-progress"><span>Step {step+1} of {steps.length}</span><strong>{steps[step][0]}</strong><ol className="reg-steps">{steps.map(([title],index)=><li key={title} className={index===step?'current':index<step?'done':''}>{index+1}</li>)}</ol><div className="reg-bar" aria-hidden="true"><span style={{width:`${((step+1)/steps.length)*100}%`}}/></div></div></div>
    <div className="reg-body">{steps.map(([title,node],index)=><div key={title} data-step className={index===step?'reg-step active':''} hidden={index!==step}>{node}</div>)}</div>
    <div className="reg-nav">{step>0&&<Button type="button" variant="outline" onClick={()=>showStep(step-1)}>Back</Button>}{optional&&<Button type="button" variant="outline" onClick={()=>showStep(step+1)}>Skip for now</Button>}{step<steps.length-1?<Button type="button" disabled={photoBusy} onClick={()=>{if(paneValid(step))showStep(step+1);}}>Continue</Button>:<Button type="submit" size="lg" pending={submitting} disabled={photoBusy}>Submit registration</Button>}</div>
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

type EditableProfile={
  name:string;phone?:string|null;email?:string|null;city?:string|null;address?:string|null;gender?:string;dob?:string|null;telegram?:string|null;
  emergencyContactName?:string|null;emergencyContactRelationship?:string|null;emergencyContactPhone?:string|null;
  educationLevel?:string|null;institution?:string|null;fieldOfStudy?:string|null;occupation?:string|null;employer?:string|null;employmentStatus?:string|null;
  experience?:number;events?:number;availability?:string|null;skills?:string[];languages?:string[];otherLanguages?:string|null;preferredEvents?:string[];
  tshirtSize?:string|null;shirtSize?:string|null;trouserSize?:string|null;shoeSize?:string|null;
  accountHolder?:string|null;accountNumber?:string|null;telebirrNumber?:string|null;idType?:string|null;idNumber?:string|null;
  idPhoto?:string|null;idPhotoFront?:string|null;idPhotoBack?:string|null;photos?:string[];instagram?:string|null;facebook?:string|null;linkedin?:string|null;tiktok?:string|null;
  correctionSections?:string[];correctionNote?:string|null;
};

const ALL_CORRECTION_SECTIONS=['about','emergency','background','skills','photos','payment','social'] as const;

function previewUsher(name:string,photos:string[]):Usher{
  return {id:0,name,city:'',gender:'',experience:0,events:0,rating:0,skills:[],languages:[],status:'Pending',available:true,photos};
}

export function ProfileEditPage({token}:{token:string}){
  const formRef=useRef<HTMLFormElement>(null);
  const errorRef=useRef<HTMLParagraphElement>(null);
  const [profile,setProfile]=useState<EditableProfile|null>(null);
  const [error,setError]=useState('');
  const [fieldErrors,setFieldErrors]=useState<Record<string,string>>({});
  const [loadError,setLoadError]=useState('');
  const [done,setDone]=useState(false);
  const [submitting,setSubmitting]=useState(false);
  const [step,setStep]=useState(0);
  const [photoJobs,setPhotoJobs]=useState(0);
  const [photos,setPhotos]=useState<File[]>([]);
  const [idPhotoFront,setIdPhotoFront]=useState<File|null>(null);
  const [idPhotoBack,setIdPhotoBack]=useState<File|null>(null);
  const [city,setCity]=useState('');
  const photoBusy=photoJobs>0;
  const markPhotoBusy=(busy:boolean)=>setPhotoJobs(n=>Math.max(0,n+(busy?1:-1)));
  useEffect(()=>{void api<EditableProfile>(`/api/public/profile/${token}`).then(data=>{setProfile(data);setCity(data.city??'');}).catch(()=>setLoadError('This correction link is not valid or has expired.'))},[token]);
  const showError=(message:string)=>{setError(message);requestAnimationFrame(()=>errorRef.current?.scrollIntoView({behavior:'smooth',block:'nearest'}));};
  const failField=(field:HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement,message:string)=>{
    field.classList.add('field-invalid');
    setFieldErrors({[field.name]:message});
    showError(message);
    try{field.focus({preventScroll:false});}catch{/* ignore */}
  };
  const showStep=(index:number,clearError=true)=>{setStep(index);if(clearError){setError('');setFieldErrors({});}const body=formRef.current?.querySelector('.reg-body');if(body)body.scrollTop=0;};
  if(done)return <PublicShell variant="reg"><SuccessCard title="Updates received" copy="Thank you. The Validity team will review your corrected profile."/></PublicShell>;
  if(loadError)return <PublicShell><div className="public-card center"><h1>Link not found</h1><p className="subtext">{loadError}</p></div></PublicShell>;
  if(!profile)return <PublicShell><LoadingMark label="Loading your profile…" /></PublicShell>;
  const requested=(profile.correctionSections?.length?profile.correctionSections:ALL_CORRECTION_SECTIONS) as string[];
  const catalog:[string,string,ReactNode][]=[
    ['about','About you',<Section title="About you"><F label="Full name" name="name" required minLength={3} maxLength={255} autoComplete="name" defaultValue={profile.name} error={fieldErrors.name}/><F label="Phone number" name="phone" type="tel" required inputMode="tel" autoComplete="tel" pattern={PHONE_PATTERN} maxLength={50} placeholder="09xx xxx xxx or +251 9xx xxx xxx" hint="Use your Ethiopian mobile number." defaultValue={profile.phone??''} error={fieldErrors.phone}/><F label="Gender" name="gender" required options={['Female','Male']} defaultValue={profile.gender||''} error={fieldErrors.gender}/><F label="Date of birth" name="dob" type="date" required max={adultDobMax()} title="You must be 18 or older" autoComplete="bday" defaultValue={profile.dob??''} error={fieldErrors.dob} onChange={e=>{const el=e.currentTarget;if(el.value&&!isAdultDob(el.value))el.setCustomValidity('Ushers must be 18 or older.');else el.setCustomValidity('');}}/><p className="full subtext">Ushers must be 18 or older.</p><F label="City" name="city" required options={cityChoices(profile.city)} defaultValue={profile.city||''} error={fieldErrors.city} onChange={e=>setCity(e.currentTarget.value)}/>{city==='Other'&&<F label="City or town" name="cityOther" required full maxLength={100} hint="Write the city if it is not in the list." error={fieldErrors.cityOther}/>}<F label="Address" name="address" required full maxLength={255} autoComplete="street-address" defaultValue={profile.address??''} error={fieldErrors.address}/><F label="Email" name="email" type="email" autoComplete="email" maxLength={255} defaultValue={profile.email??''} error={fieldErrors.email}/><F label="Telegram username" name="telegram" placeholder="@username" maxLength={100} defaultValue={profile.telegram??''} error={fieldErrors.telegram}/></Section>],
    ['emergency','Emergency',<Section title="Emergency contact"><F label="Contact name" name="ecName" required full maxLength={255} autoComplete="off" defaultValue={profile.emergencyContactName??''} error={fieldErrors.ecName}/><F label="Relationship" name="ecRel" required maxLength={100} defaultValue={profile.emergencyContactRelationship??''} error={fieldErrors.ecRel}/><F label="Phone number" name="ecPhone" type="tel" required inputMode="tel" pattern={PHONE_PATTERN} maxLength={50} placeholder="09xx xxx xxx" defaultValue={profile.emergencyContactPhone??''} error={fieldErrors.ecPhone}/></Section>],
    ['background','Background',<Section title="Background"><F label="Highest education level" name="edu" options={['High school','Diploma','Bachelor’s degree','Master’s degree','Other']} defaultValue={profile.educationLevel??''}/><F label="Institution" name="inst" defaultValue={profile.institution??''}/><F label="Field of study" name="field" full defaultValue={profile.fieldOfStudy??''}/><F label="Current occupation" name="occ" defaultValue={profile.occupation??''}/><F label="Employer / company" name="employer" defaultValue={profile.employer??''}/><F label="Employment status" name="empStatus" full options={['Student','Employed','Self-employed','Unemployed','Freelancer']} defaultValue={profile.employmentStatus??''}/></Section>],
    ['skills','Languages & skills',<Section title="Languages & skills"><Checks label="Languages you speak" name="languages" items={['Amharic','English','Afaan Oromo','Tigrinya','Somali','Arabic','Other']} selected={profile.languages}/><F label="Other languages" name="otherLang" full defaultValue={profile.otherLanguages??''}/><Checks label="Skills" name="skills" items={['Registration','Guest Relations','VIP Handling','Crowd Management','Hospitality','Brand Ambassador','Ticketing','Check-in','Event Protocol','Public Speaking','Customer Service','Team Leader','Supervisor','Technical Support']} selected={profile.skills}/><Checks label="Preferred event types" name="prefs" items={['Corporate','Conference','Exhibition','Concert','Festival','Product Launch','Brand Activation','Wedding','VIP','Government','Sports','Retail/BTL']} selected={profile.preferredEvents}/><F label="General availability" name="availability" full options={['Weekdays','Weekends','Weekdays & weekends','Evenings only']} defaultValue={profile.availability??''}/><F label="Years of event experience" name="years" type="number" min="0" defaultValue={profile.experience??0} error={fieldErrors.years}/><F label="Number of events worked" name="events" type="number" min="0" defaultValue={profile.events??0} error={fieldErrors.events}/></Section>],
    ['photos','Photos & sizes',<Section title="Photos & sizes"><p className="full subtext">Current photos stay unless you upload replacements. New images must be JPEG, PNG, or WebP under 2 MB.</p>{(profile.photos?.length??0)>0&&<div className="full"><UsherPhotos usher={previewUsher(profile.name,profile.photos??[])}/></div>}<PhotoPicker files={photos} onChange={setPhotos} error={fieldErrors.photo1} onBusy={markPhotoBusy} onReject={message=>{setError(message);setFieldErrors(message?{photo1:message}:{})}}/><F label="T-shirt size" name="tshirt" options={['XS','S','M','L','XL','XXL']} defaultValue={profile.tshirtSize??''}/><F label="Shirt size" name="shirt" options={['XS','S','M','L','XL','XXL']} defaultValue={profile.shirtSize??''}/><F label="Trouser size" name="trouser" maxLength={20} defaultValue={profile.trouserSize??''}/><F label="Shoe size" name="shoe" maxLength={20} defaultValue={profile.shoeSize??''}/></Section>],
    ['payment','Payment & ID',<Section title="Payment & ID (private)"><p className="full subtext">Only Validity administrators can see this. It is never shared with clients.</p><div className="full"><small className="text-muted-foreground">Bank</small><strong>CBE</strong><input type="hidden" name="bank" value="CBE"/></div><F label="Account holder name" name="holder" required full maxLength={255} defaultValue={profile.accountHolder??''} error={fieldErrors.holder}/><F label="CBE account number" name="acct" required full placeholder="1000xxxxxxxxx" pattern="1000[0-9]+" inputMode="numeric" maxLength={100} title="Must start with 1000 and contain digits only" hint="Usually 13 digits, starting with 1000." defaultValue={(profile.accountNumber??'').replace(/\D/g,'')} error={fieldErrors.acct}/><F label="Telebirr number" name="telebirr" type="tel" full placeholder="Usually your phone number" inputMode="tel" pattern={PHONE_PATTERN} maxLength={50} defaultValue={profile.telebirrNumber??''} error={fieldErrors.telebirr}/><F label="ID type" name="idType" required options={['Kebele ID','National ID (Fayda)','Passport','Driver’s licence']} full defaultValue={profile.idType??''} error={fieldErrors.idType}/><F label="ID / passport number" name="idNo" full maxLength={100} defaultValue={profile.idNumber??''} error={fieldErrors.idNo}/>{(profile.idPhotoFront||profile.idPhoto||profile.idPhotoBack)&&<div className="id-photo-row full">{(profile.idPhotoFront||profile.idPhoto)&&<div><small className="block text-muted-foreground mb-2">Current ID front</small><UsherPhotos usher={previewUsher(profile.name,[profile.idPhotoFront||profile.idPhoto!])}/></div>}{profile.idPhotoBack&&<div><small className="block text-muted-foreground mb-2">Current ID back</small><UsherPhotos usher={previewUsher(profile.name,[profile.idPhotoBack])}/></div>}</div>}<div className="id-photo-row full"><IdPhotoPicker label="ID front" file={idPhotoFront} error={fieldErrors.idPhotoFront} onChange={setIdPhotoFront} required={!(profile.idPhotoFront||profile.idPhoto)} hint="Front of your ID — leave empty to keep the current photo" onBusy={markPhotoBusy} onReject={message=>{setError(message);setFieldErrors(current=>({...current,idPhotoFront:message}));}}/><IdPhotoPicker label="ID back" file={idPhotoBack} error={fieldErrors.idPhotoBack} onChange={setIdPhotoBack} required={!profile.idPhotoBack} hint="Back of your ID — leave empty to keep the current photo" onBusy={markPhotoBusy} onReject={message=>{setError(message);setFieldErrors(current=>({...current,idPhotoBack:message}));}}/></div><p className="full subtext">Upload replacements only if you need to change an ID photo.</p></Section>],
    ['social','Social',<Section title="Social"><F label="Instagram" name="ig" maxLength={2048} defaultValue={profile.instagram??''} error={fieldErrors.ig}/><F label="Facebook" name="fb" maxLength={2048} defaultValue={profile.facebook??''} error={fieldErrors.fb}/><F label="LinkedIn" name="li" maxLength={2048} defaultValue={profile.linkedin??''} error={fieldErrors.li}/><F label="TikTok" name="tt" maxLength={2048} defaultValue={profile.tiktok??''} error={fieldErrors.tt}/></Section>],
  ];
  const steps=catalog.filter(([id])=>requested.includes(id));
  const paneValid=(index:number)=>{
    if(photoBusy){showError('Wait for your photos to finish preparing before continuing.');return false;}
    const id=steps[index]?.[0];
    if(id==='photos'&&photos.some(file=>file.size>MAX_IMAGE_BYTES)){setFieldErrors({photo1:'Each photo must be 2 MB or smaller.'});showError('Each photo must be 2 MB or smaller.');return false;}
    const pane=formRef.current?.querySelectorAll<HTMLElement>('[data-step]')[index];
    if(!pane)return true;
    const wasHidden=pane.hidden;
    pane.hidden=false;
    pane.querySelectorAll('.field-invalid').forEach(node=>node.classList.remove('field-invalid'));
    const dob=pane.querySelector<HTMLInputElement>('input[name=dob]');
    if(dob){
      if(dob.value&&!isAdultDob(dob.value))dob.setCustomValidity('Ushers must be 18 or older.');
      else dob.setCustomValidity('');
    }
    const fields=pane.querySelectorAll<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>('input,select,textarea');
    for(const field of fields){
      if(field instanceof HTMLInputElement&&(field.type==='file'||field.type==='hidden'))continue;
      if(field.checkValidity())continue;
      const message=field.name==='dob'&&!isAdultDob((field as HTMLInputElement).value)?'Ushers must be 18 or older.':fieldMessage(field);
      failField(field,message);
      pane.hidden=wasHidden;
      return false;
    }
    if(id==='about'&&dob&&dob.value&&!isAdultDob(dob.value)){
      dob.setCustomValidity('Ushers must be 18 or older.');
      failField(dob,'Ushers must be 18 or older.');
      pane.hidden=wasHidden;
      return false;
    }
    if(id==='payment'){
      if(!(profile.idPhotoFront||profile.idPhoto)&&!idPhotoFront){setFieldErrors({idPhotoFront:'Attach a clear photo of the front of your ID to continue.'});showError('Attach a clear photo of the front of your ID to continue.');pane.hidden=wasHidden;return false;}
      if(!profile.idPhotoBack&&!idPhotoBack){setFieldErrors({idPhotoBack:'Attach a clear photo of the back of your ID to continue.'});showError('Attach a clear photo of the back of your ID to continue.');pane.hidden=wasHidden;return false;}
      if((idPhotoFront&&idPhotoFront.size>MAX_IMAGE_BYTES)||(idPhotoBack&&idPhotoBack.size>MAX_IMAGE_BYTES)){setFieldErrors({idPhotoFront:'Each ID photo must be 2 MB or smaller.'});showError('Each ID photo must be 2 MB or smaller.');pane.hidden=wasHidden;return false;}
    }
    pane.hidden=wasHidden;
    setError('');
    setFieldErrors({});
    return true;
  };
  const submitCorrection=async(form:HTMLFormElement)=>{
    for(let index=0;index<steps.length;index++){
      if(!paneValid(index)){showStep(index,false);return;}
    }
    setError('');setSubmitting(true);
    const body=withoutEmptyFiles(new FormData(form));
    if(body.get('city')==='Other'){
      const other=String(body.get('cityOther')??'').trim();
      if(other)body.set('city',other);
    }
    body.delete('cityOther');
    const account=String(body.get('acct')??'').replace(/\D/g,'');
    if(account)body.set('acct',account);
    (['photo1','photo2','photo3','idPhoto','idPhotoFront','idPhotoBack'] as const).forEach(key=>body.delete(key));
    photos.slice(0,3).forEach((file,index)=>body.append(`photo${index+1}`,file));
    if(idPhotoFront)body.append('idPhotoFront',idPhotoFront);
    if(idPhotoBack)body.append('idPhotoBack',idPhotoBack);
    try{
      await api(`/api/public/profile/${token}`,{method:'POST',body});
      setDone(true);window.scrollTo(0,0);
    }catch(err){
      const mapped=apiFieldErrorMap(err);
      const field=Object.keys(mapped)[0]??firstApiField(err);
      const message=mapped[field??'']||formatApiError(err);
      setFieldErrors(mapped);
      showError(message);
      if(field){
        const target=steps.findIndex(([id])=>id===fieldSection(field.split('.')[0]??field));
        if(target>=0)showStep(target,false);
      }
    }finally{
      setSubmitting(false);
    }
  };
  const current=steps[step]??steps[0];
  return <PublicShell variant="reg"><form ref={formRef} className="reg-form profile-edit-form" noValidate onSubmit={e=>{e.preventDefault();if(submitting)return;if(step<steps.length-1){if(paneValid(step))showStep(step+1);return;}if(!paneValid(step))return;void submitCorrection(e.currentTarget);}}>
    <div className="reg-top"><img src="/validity-events-logo.svg" alt="Validity Events" className="reg-logo"/><p className="reg-tagline">Update your usher profile</p>{profile.correctionNote&&<p className="correction-note" role="note"><strong>From Validity: </strong>{profile.correctionNote}</p>}{error&&<p ref={errorRef} role="alert" aria-live="assertive" className="reg-error">{error}</p>}<div className="reg-progress"><span>Step {step+1} of {steps.length}</span><strong>{current?.[1]}</strong><ol className="reg-steps">{steps.map(([id],index)=><li key={id} className={index===step?'current':index<step?'done':''}>{index+1}</li>)}</ol><div className="reg-bar" aria-hidden="true"><span style={{width:`${((step+1)/Math.max(steps.length,1))*100}%`}}/></div></div></div>
    <div className="reg-body">{steps.map(([id,title,node],index)=><div key={id} data-step className={index===step?'reg-step active':''} hidden={index!==step}>{node}</div>)}</div>
    <div className="reg-nav">{step>0&&<Button type="button" variant="outline" onClick={()=>showStep(step-1)}>Back</Button>}{step<steps.length-1?<Button type="button" disabled={photoBusy} onClick={()=>{if(paneValid(step))showStep(step+1);}}>Continue</Button>:<Button type="submit" size="lg" pending={submitting} disabled={photoBusy}>Submit corrections</Button>}</div>
  </form></PublicShell>;
}

export function LoginPage(){
  const [email,setEmail]=useState('');const [password,setPassword]=useState('');const [error,setError]=useState('');const [pending,setPending]=useState(false);
  return <PublicShell><form className="public-card narrow" onSubmit={e=>{e.preventDefault();setError('');setPending(true);void login(email,password).then(()=>{window.location.assign('/')}).catch((cause:unknown)=>{setError(formatApiError(cause));setPending(false)})}}>
    <h1>Sign in</h1><p className="subtext mb-6">Validity administrators</p>
    {error&&<p role="alert" className="text-warning mb-4">{error}</p>}
    <div className="form-grid"><label className="full">Email<input type="email" required autoComplete="username" value={email} onChange={e=>setEmail(e.target.value)} placeholder="admin@validity.et"/></label><label className="full">Password<input type="password" required autoComplete="current-password" value={password} onChange={e=>setPassword(e.target.value)}/></label><Button type="submit" className="full" pending={pending}>Sign in</Button></div>
  </form></PublicShell>;
}
