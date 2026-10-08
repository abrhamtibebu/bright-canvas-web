import { createContext, useContext, type ReactNode } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouterState } from "@tanstack/react-router";
import { api, logout } from "@/lib/api";
import type { Admin, Assignment, Project, Reports, Usher, WorkspaceInfo } from "@/lib/demo-data";

const publicPrefixes = ["/register", "/respond", "/confirm", "/client", "/rate", "/login"];

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
  requestCorrection: (id: number) => Promise<void>;
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
  invite: (projectId: number, usherIds: number[]) => Promise<void>;
  setResponse: (assignmentId: number, response: string) => Promise<void>;
  setAttendance: (assignmentId: number, attendance: string) => Promise<void>;
  selectTeam: (projectId: number, usherIds: number[]) => Promise<void>;
  rateUsher: (projectId: number, usherId: number, score: string, comment: string) => Promise<void>;
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
    requestCorrection: async (id) => {
      await api(`/api/ushers/${id}`, {
        method: "PATCH",
        body: JSON.stringify({ status: "Correction Required" }),
      });
      await refresh();
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
      await api(`/api/projects/${projectId}/invitations`, {
        method: "POST",
        body: JSON.stringify({ usher_ids: usherIds }),
      });
      await refresh();
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
    logout: async () => {
      await logout();
      queryClient.clear();
    },
  };

  return <Context.Provider value={value}>{children}</Context.Provider>;
}
