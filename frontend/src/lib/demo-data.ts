export type UsherExperience = {
  event: string | null;
  client: string | null;
  role: string | null;
  eventType?: string | null;
};

export type UsherReference = {
  name: string | null;
  organization: string | null;
  relationship: string | null;
  phone: string | null;
  email: string | null;
  notes: string | null;
};

export type Usher = {
  id: number;
  name: string;
  phone?: string | null;
  email?: string | null;
  city: string;
  address?: string | null;
  gender: string;
  dateOfBirth?: string | null;
  telegram?: string | null;
  emergencyContactName?: string | null;
  emergencyContactRelationship?: string | null;
  emergencyContactPhone?: string | null;
  educationLevel?: string | null;
  institution?: string | null;
  fieldOfStudy?: string | null;
  occupation?: string | null;
  employer?: string | null;
  employmentStatus?: string | null;
  experience: number;
  events: number;
  rating: number;
  availability?: string | null;
  skills: string[];
  languages: string[];
  otherLanguages?: string | null;
  status: string;
  available: boolean;
  photos?: string[];
  preferredEvents?: string[];
  tshirtSize?: string | null;
  shirtSize?: string | null;
  trouserSize?: string | null;
  shoeSize?: string | null;
  paymentMethod?: string | null;
  bankName?: string | null;
  accountHolder?: string | null;
  accountNumber?: string | null;
  telebirrNumber?: string | null;
  idType?: string | null;
  idNumber?: string | null;
  instagram?: string | null;
  facebook?: string | null;
  linkedin?: string | null;
  tiktok?: string | null;
  experiences?: UsherExperience[];
  references?: UsherReference[];
};

export type Project = {
  id: number;
  name: string;
  client: string;
  date: string;
  location: string;
  required: number;
  confirmed: number;
  status: string;
  callTime: string;
  endTime: string;
  compensation: string;
  transport: string;
  dressCode: string;
  startsOn?: string | null;
  endsOn?: string | null;
  transportProvided?: boolean;
  foodProvided?: boolean;
  availabilityToken: string;
  clientToken: string;
  ratingToken: string;
};

export type Assignment = {
  id: number;
  projectId: number;
  usherId: number;
  projectName: string;
  projectDate: string;
  location: string;
  callTime: string;
  endTime: string;
  compensation: string;
  transport: string;
  transportProvided?: boolean;
  foodProvided?: boolean;
  dressCode: string;
  role: string;
  response: string;
  attendance: string;
  clientSelected: boolean;
};

export type WorkspaceInfo = {
  company: string;
  workspace: string;
  timezone: string;
  currency: string;
  registrationToken: string;
};

export type Admin = { name: string; email: string };

export type Reports = {
  averagePerformance: string;
  attendanceRate: string;
  confirmationRate: string;
  clientSatisfaction: string;
  adminRatings: Record<string, string>;
};

export function initials(name: string) {
  return name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("");
}
