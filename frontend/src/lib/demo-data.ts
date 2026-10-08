export type Usher = {
  id: number;
  name: string;
  phone?: string | null;
  city: string;
  gender: string;
  experience: number;
  events: number;
  rating: number;
  skills: string[];
  languages: string[];
  status: string;
  available: boolean;
  preferredEvents?: string[];
  experiences?: { event: string | null; client: string | null; role: string | null }[];
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
