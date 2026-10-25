import { createContext, useContext, type ReactNode } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouterState } from "@tanstack/react-router";
import { api, logout } from "@/lib/api";
import type { Admin, Assignment, Project, Reports, Usher, WorkspaceInfo } from "@/lib/demo-data";
import type { SmsStatus } from "@/lib/sms";

const publicPrefixes = ["/register", "/respond", "/confirm", "/client", "/rate", "/login", "/profile"];

type UsherUpdateInput = Partial<{
  status: string;
  name: string;
  phone: string | null;
  email: string | null;
  gender: string | null;
  dob: string | null;
  city: string | null;
  address: string | null;
  telegram: string | null;
  emergencyContactName: string | null;
  emergencyContactRelationship: string | null;
  emergencyContactPhone: string | null;
  educationLevel: string | null;
  institution: string | null;
  fieldOfStudy: string | null;
  occupation: string | null;
  employer: string | null;
  employmentStatus: string | null;
  experience: number;
  events: number;
  availability: string | null;
  skills: string[];
  languages: string[];
  otherLanguages: string | null;
  preferredEvents: string[];
  tshirtSize: string | null;
  shirtSize: string | null;
  trouserSize: string | null;
  shoeSize: string | null;
  accountHolder: string | null;
  accountNumber: string | null;
  telebirrNumber: string | null;
  idType: string | null;
  idNumber: string | null;
  instagram: string | null;
  facebook: string | null;
  linkedin: string | null;
  tiktok: string | null;
  available: boolean;
}>;

type WorkspaceData = {
  me: Admin | null;
  ushers: Usher[];
  projects: Project[];
  assignments: Assignment[];
  workspace: WorkspaceInfo | null;
  reports: Reports | null;
  ready: boolean;
  unauthenticated: boolean;
  approveUsher: (id: number) => Promise<void>;
  requestCorrection: (id: number, sections: string[], note?: string) => Promise<Usher>;
  updateUsher: (id: number, input: UsherUpdateInput) => Promise<Usher>;
  addUsher: (input: { name: string; phone: string; city: string }) => Promise<void>;
  addUsherPhoto: (id: number, file: File) => Promise<void>;
  deleteUsherPhoto: (path: string) => Promise<void>;
  deleteUsher: (id: number) => Promise<void>;
  deleteProject: (id: number) => Promise<void>;
  deleteAssignment: (id: number) => Promise<void>;
  createProject: (input: {
    name: string;
    client: string;
    starts_on: string;
    ends_on: string;
    location: string;
    required: number;
    call_time: string;
    end_time: string;
    transport_provided: boolean;
    food_provided: boolean;
    compensation: string;
    dress_code: string;
  }) => Promise<void>;
  invite: (projectId: number, usherIds: number[]) => Promise<{ prepared: number; sms?: SmsStatus }>;
  resendCorrectionSms: (usherId: number) => Promise<Usher>;
  resendAvailabilitySms: (projectId: number) => Promise<{ prepared: number; sms?: SmsStatus }>;
  setResponse: (assignmentId: number, response: string) => Promise<void>;
  setAttendance: (assignmentId: number, attendance: string) => Promise<void>;
  setPaymentStatus: (assignmentId: number, paymentStatus: string) => Promise<void>;
  selectTeam: (projectId: number, usherIds: number[]) => Promise<void>;
  rateUsher: (projectId: number, usherId: number, score: string, comment: string) => Promise<void>;
  setRegistrationOpen: (open: boolean) => Promise<void>;
  rotateRegistrationLink: () => Promise<WorkspaceInfo>;
  rotateProjectLink: (projectId: number, kind: "availability" | "client" | "rating") => Promise<void>;
  revokeCorrectionLink: (usherId: number) => Promise<Usher>;
  rotateCorrectionLink: (usherId: number) => Promise<Usher>;
  logout: () => Promise<void>;
};

const Context = createContext<WorkspaceData | null>(null);

export function useDemo() {
  const value = useContext(Context);
  if (!value) throw new Error("Workspace provider missing");
  return value;
}

export function DemoProvider({ children }: { children: ReactNode }) {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const isPublic = publicPrefixes.some((prefix) => pathname.startsWith(prefix));
  const enabled = typeof window !== "undefined" && !isPublic;
  const queryClient = useQueryClient();
  const me = useQuery({
    queryKey: ["me"],
    queryFn: () => api<Admin>("/api/me"),
    retry: false,
    enabled,
  });
  const authed = enabled && me.isSuccess;
  const ushers = useQuery({
    queryKey: ["ushers"],
    queryFn: () => api<Usher[]>("/api/ushers"),
    enabled: authed,
  });
  const projects = useQuery({
    queryKey: ["projects"],
    queryFn: () => api<Project[]>("/api/projects"),
    enabled: authed,
  });
  const assignments = useQuery({
    queryKey: ["assignments"],
    queryFn: () => api<Assignment[]>("/api/assignments"),
    enabled: authed,
  });
  const workspace = useQuery({
    queryKey: ["workspace"],
    queryFn: () => api<WorkspaceInfo>("/api/workspace"),
    enabled: authed,
  });
  const reports = useQuery({
    queryKey: ["reports"],
    queryFn: () => api<Reports>("/api/reports"),
    enabled: authed,
  });

  async function refresh() {
    await queryClient.invalidateQueries();
  }

  const value: WorkspaceData = {
    me: me.data ?? null,
    ushers: ushers.data ?? [],
    projects: projects.data ?? [],
    assignments: assignments.data ?? [],
    workspace: workspace.data ?? null,
    reports: reports.data ?? null,
    ready:
      isPublic ||
      (me.isSuccess &&
        ushers.isSuccess &&
        projects.isSuccess &&
        assignments.isSuccess &&
        workspace.isSuccess &&
        reports.isSuccess),
    unauthenticated: enabled && me.isError,
    approveUsher: async (id) => {
      await api(`/api/ushers/${id}`, { method: "PATCH", body: JSON.stringify({ status: "Active" }) });
      await refresh();
    },
    requestCorrection: async (id, sections, note) => {
      const updated = await api<Usher>(`/api/ushers/${id}`, {
        method: "PATCH",
        body: JSON.stringify({
          status: "Correction Required",
          correctionSections: sections,
          correctionNote: note?.trim() || null,
        }),
      });
      await refresh();
      return updated;
    },
    updateUsher: async (id, input) => {
      const updated = await api<Usher>(`/api/ushers/${id}`, {
        method: "PATCH",
        body: JSON.stringify(input),
      });
      await refresh();
      return updated;
    },
    addUsher: async (input) => {
      await api("/api/ushers", { method: "POST", body: JSON.stringify(input) });
      await refresh();
    },
    addUsherPhoto: async (id, file) => {
      const body = new FormData();
      body.append("photo", file);
      await api(`/api/ushers/${id}/photos`, { method: "POST", body });
      await refresh();
    },
    deleteUsherPhoto: async (path) => {
      await api(path, { method: "DELETE" });
      await refresh();
    },
    deleteUsher: async (id) => {
      await api(`/api/ushers/${id}`, { method: "DELETE" });
      await refresh();
    },
    deleteProject: async (id) => {
      await api(`/api/projects/${id}`, { method: "DELETE" });
      await refresh();
    },
    deleteAssignment: async (id) => {
      await api(`/api/assignments/${id}`, { method: "DELETE" });
      await refresh();
    },
    createProject: async (input) => {
      await api("/api/projects", { method: "POST", body: JSON.stringify(input) });
      await refresh();
    },
    invite: async (projectId, usherIds) => {
      const result = await api<{ prepared: number; sms?: SmsStatus }>(`/api/projects/${projectId}/invitations`, {
        method: "POST",
        body: JSON.stringify({ usher_ids: usherIds }),
      });
      await refresh();
      return result;
    },
    resendCorrectionSms: async (usherId) => {
      const updated = await api<Usher>(`/api/ushers/${usherId}/correction-link/sms`, { method: "POST" });
      await refresh();
      return updated;
    },
    resendAvailabilitySms: async (projectId) => {
      const result = await api<{ prepared: number; sms?: SmsStatus }>(`/api/projects/${projectId}/invitations/sms`, {
        method: "POST",
      });
      await refresh();
      return result;
    },
    setResponse: async (assignmentId, response) => {
      await api(`/api/assignments/${assignmentId}`, {
        method: "PATCH",
        body: JSON.stringify({ response }),
      });
      await refresh();
    },
    setAttendance: async (assignmentId, attendance) => {
      await api(`/api/assignments/${assignmentId}`, {
        method: "PATCH",
        body: JSON.stringify({ attendance }),
      });
      await refresh();
    },
    setPaymentStatus: async (assignmentId, paymentStatus) => {
      await api(`/api/assignments/${assignmentId}`, {
        method: "PATCH",
        body: JSON.stringify({ payment_status: paymentStatus }),
      });
      await refresh();
    },
    selectTeam: async (projectId, usherIds) => {
      await api(`/api/projects/${projectId}/selection`, {
        method: "POST",
        body: JSON.stringify({ usher_ids: usherIds }),
      });
      await refresh();
    },
    rateUsher: async (projectId, usherId, score, comment) => {
      await api(`/api/projects/${projectId}/ratings`, {
        method: "POST",
        body: JSON.stringify({ usher_id: usherId, score: Number(score), comment }),
      });
      await refresh();
    },
    setRegistrationOpen: async (open) => {
      await api("/api/workspace", { method: "PATCH", body: JSON.stringify({ registrationOpen: open }) });
      await refresh();
    },
    rotateRegistrationLink: async () => {
      const updated = await api<WorkspaceInfo>("/api/workspace/registration-link/rotate", { method: "POST" });
      await refresh();
      return updated;
    },
    rotateProjectLink: async (projectId, kind) => {
      await api(`/api/projects/${projectId}/links/${kind}/rotate`, { method: "POST" });
      await refresh();
    },
    revokeCorrectionLink: async (usherId) => {
      const updated = await api<Usher>(`/api/ushers/${usherId}/correction-link/revoke`, { method: "POST" });
      await refresh();
      return updated;
    },
    rotateCorrectionLink: async (usherId) => {
      const updated = await api<Usher>(`/api/ushers/${usherId}/correction-link/rotate`, { method: "POST" });
      await refresh();
      return updated;
    },
    logout: async () => {
      await logout();
      queryClient.clear();
    },
  };

  return <Context.Provider value={value}>{children}</Context.Provider>;
}
