export type Usher = {id:number;name:string;city:string;gender:string;experience:number;events:number;rating:number;skills:string[];languages:string[];status:string;available:boolean};
export type Project = {id:number;name:string;client:string;date:string;location:string;required:number;confirmed:number;status:string};
export const initialUshers:Usher[] = [
{id:1,name:'Hana Tesfaye',city:'Addis Ababa',gender:'Female',experience:3,events:24,rating:4.9,skills:['Guest Relations','VIP Handling'],languages:['Amharic','English'],status:'Active',available:true},
{id:2,name:'Abebe Kebede',city:'Addis Ababa',gender:'Male',experience:4,events:32,rating:4.8,skills:['Registration','Team Leader'],languages:['Amharic','English'],status:'Active',available:true},
{id:3,name:'Meron Alemayehu',city:'Addis Ababa',gender:'Female',experience:2,events:18,rating:4.7,skills:['Hospitality','Registration'],languages:['Amharic','English'],status:'Active',available:true},
{id:4,name:'Dawit Bekele',city:'Adama',gender:'Male',experience:3,events:21,rating:4.8,skills:['Crowd Management','Ticketing'],languages:['Amharic','Afaan Oromo'],status:'Active',available:false},
{id:5,name:'Selam Getachew',city:'Addis Ababa',gender:'Female',experience:2,events:15,rating:4.6,skills:['Brand Ambassador','Guest Relations'],languages:['Amharic','English'],status:'Active',available:true},
{id:6,name:'Rahel Tadesse',city:'Addis Ababa',gender:'Female',experience:1,events:6,rating:4.5,skills:['Registration'],languages:['Amharic','English'],status:'Pending',available:true},
{id:7,name:'Yonas Girma',city:'Addis Ababa',gender:'Male',experience:2,events:11,rating:4.6,skills:['Ticketing','Guest Relations'],languages:['Amharic','English'],status:'Pending',available:true},
{id:8,name:'Bethlehem Assefa',city:'Addis Ababa',gender:'Female',experience:1,events:4,rating:4.4,skills:['Hospitality'],languages:['Amharic','English'],status:'Pending',available:true},
{id:9,name:'Nahom Solomon',city:'Adama',gender:'Male',experience:2,events:14,rating:4.7,skills:['Registration','VIP Handling'],languages:['Amharic','English'],status:'Active',available:true},
{id:10,name:'Tigist Mekonnen',city:'Addis Ababa',gender:'Female',experience:4,events:29,rating:4.9,skills:['VIP Handling','Team Leader'],languages:['Amharic','English'],status:'Active',available:true},
];
export const initialProjects:Project[]=[
{id:1,name:'Big 5 Construct Ethiopia',client:'dmg events',date:'Nov 20 – 23, 2026',location:'Addis International Convention Center',required:20,confirmed:13,status:'Upcoming'},
{id:2,name:'Telebirr Anniversary',client:'Ethio telecom',date:'Nov 28, 2026',location:'Millennium Hall, Addis Ababa',required:16,confirmed:8,status:'Upcoming'},
{id:3,name:'Corporate Leadership Forum',client:'Demo client',date:'Oct 15, 2026',location:'Sheraton Addis',required:8,confirmed:8,status:'Active'},
];
export function initials(name:string){return name.split(' ').map(n=>n[0]).slice(0,2).join('')}
