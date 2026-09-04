import { type ReactNode, useEffect, useRef } from 'react';
import { ClerkProvider, Show, SignIn, SignUp, useClerk } from '@clerk/react';
import { publishableKeyFromHost } from '@clerk/react/internal';
import { shadcn } from '@clerk/themes';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useQueryClient } from '@tanstack/react-query';
import { ErrorBoundary } from '@/components/error-boundary';
import { HospitalShell } from '@/components/hospital-shell';
import { Toaster } from '@/components/ui/toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import Appointments from '@/pages/appointments';
import Dashboard from '@/pages/dashboard';
import NotFound from '@/pages/not-found';
import PatientDetail from '@/pages/patient-detail';
import Patients from '@/pages/patients';
import Reports from '@/pages/reports';
import Settings from '@/pages/settings';
import Home from '@/pages/home';
import PatientPortal from '@/pages/patient-portal';
import { useGetAuthMe } from '@workspace/api-client-react';
import {
  Route,
  Switch,
  Router as WouterRouter,
  useLocation,
} from 'wouter';

const queryClient = new QueryClient();
const clerkPubKey = publishableKeyFromHost(window.location.hostname, import.meta.env.VITE_CLERK_PUBLISHABLE_KEY);
const clerkProxyUrl = import.meta.env.VITE_CLERK_PROXY_URL;
const basePath = import.meta.env.BASE_URL.replace(/\/$/, '');

function stripBase(path: string) {
  return basePath && path.startsWith(basePath) ? path.slice(basePath.length) || '/' : path;
}

const clerkAppearance = {
  theme: shadcn,
  cssLayerName: 'clerk',
  options: { logoPlacement: 'inside' as const, logoLinkUrl: basePath || '/', logoImageUrl: `${window.location.origin}${basePath}/logo.svg` },
  variables: { colorPrimary: '#1e706c', colorForeground: '#21413f', colorMutedForeground: '#637774', colorDanger: '#b94e43', colorBackground: '#fffdf8', colorInput: '#fffdf8', colorInputForeground: '#21413f', colorNeutral: '#d9d1c2', fontFamily: 'DM Sans', borderRadius: '0.7rem' },
  elements: {
    rootBox: 'w-full flex justify-center', cardBox: 'bg-[#fffdf8] rounded-2xl w-[440px] max-w-full overflow-hidden', card: '!shadow-none !border-0 !bg-transparent !rounded-none', footer: '!shadow-none !border-0 !bg-transparent !rounded-none',
    headerTitle: 'text-[#21413f]', headerSubtitle: 'text-[#637774]', socialButtonsBlockButtonText: 'text-[#21413f]', formFieldLabel: 'text-[#21413f]', footerActionLink: 'text-[#1e706c]', footerActionText: 'text-[#637774]', dividerText: 'text-[#637774]', identityPreviewEditButton: 'text-[#1e706c]', formFieldSuccessText: 'text-[#1e706c]', alertText: 'text-[#b94e43]',
    logoBox: 'rounded-xl', logoImage: 'rounded-xl', socialButtonsBlockButton: 'border-[#d9d1c2] bg-[#fffdf8]', formButtonPrimary: 'bg-[#1e706c] hover:bg-[#185b58]', formFieldInput: 'border-[#d9d1c2] bg-[#fffdf8] text-[#21413f]', footerAction: 'border-[#d9d1c2]', dividerLine: 'bg-[#d9d1c2]', alert: 'border-[#efc7bf] bg-[#fff3f0]', otpCodeFieldInput: 'border-[#d9d1c2]', formFieldRow: 'text-[#21413f]', main: 'bg-transparent',
  },
};

function Router() {
  return (
    <HospitalShell>
      <RoutedErrorBoundary>
        <Switch>
          <Route path="/" component={Dashboard} />
          <Route path="/consultas" component={Appointments} />
          <Route path="/pacientes" component={Patients} />
          <Route path="/pacientes/:id" component={PatientDetail} />
          <Route path="/relatorios" component={Reports} />
          <Route path="/definicoes" component={Settings} />
          <Route component={NotFound} />
        </Switch>
      </RoutedErrorBoundary>
    </HospitalShell>
  );
}

function HomeRedirect() {
  return <><Show when="signed-in"><RedirectToPortal /></Show><Show when="signed-out"><Home /></Show></>;
}

function RedirectToPortal() {
  const [, setLocation] = useLocation();
  useEffect(() => { setLocation('/portal', { replace: true }); }, [setLocation]);
  return <div className="flex min-h-[100dvh] items-center justify-center bg-background text-sm text-muted-foreground">A abrir o seu portal…</div>;
}

function StaffRouter() {
  return <HospitalShell><RoutedErrorBoundary><Switch><Route path="/consultas" component={Appointments} /><Route path="/pacientes" component={Patients} /><Route path="/pacientes/:id" component={PatientDetail} /><Route path="/relatorios" component={Reports} /><Route path="/definicoes" component={Settings} /><Route path="/" component={Dashboard} /><Route component={NotFound} /></Switch></RoutedErrorBoundary></HospitalShell>;
}

function AuthenticatedRoutes() {
  const authMe = useGetAuthMe();
  if (authMe.isLoading) return <div className="flex min-h-[100dvh] items-center justify-center bg-background text-sm text-muted-foreground">A validar o acesso…</div>;
  return <Switch><Route path="/" component={HomeRedirect} /><Route path="/portal" component={PatientPortal} />{authMe.data?.role !== 'patient' && <Route component={StaffRouter} />}<Route component={PatientPortal} /></Switch>;
}

function SignInPage() {
  return <div className="flex min-h-[100dvh] items-center justify-center bg-background px-4"><SignIn routing="path" path={`${basePath}/sign-in`} signUpUrl={`${basePath}/sign-up`} /></div>;
}

function SignUpPage() {
  return <div className="flex min-h-[100dvh] items-center justify-center bg-background px-4"><SignUp routing="path" path={`${basePath}/sign-up`} signInUrl={`${basePath}/sign-in`} /></div>;
}

function ClerkQueryClientCacheInvalidator() {
  const { addListener } = useClerk();
  const client = useQueryClient();
  const previousUserId = useRef<string | null | undefined>(undefined);
  useEffect(() => addListener(({ user }) => { const userId = user?.id ?? null; if (previousUserId.current !== undefined && previousUserId.current !== userId) client.clear(); previousUserId.current = userId; }), [addListener, client]);
  return null;
}

function ClerkRoutes() {
  const [, setLocation] = useLocation();
  return <ClerkProvider publishableKey={clerkPubKey} proxyUrl={clerkProxyUrl} appearance={clerkAppearance} signInUrl={`${basePath}/sign-in`} signUpUrl={`${basePath}/sign-up`} routerPush={(to) => setLocation(stripBase(to))} routerReplace={(to) => setLocation(stripBase(to), { replace: true })}><QueryClientProvider client={queryClient}><ClerkQueryClientCacheInvalidator /><Switch><Route path="/sign-in/*?" component={SignInPage} /><Route path="/sign-up/*?" component={SignUpPage} /><Route path="/"><HomeRedirect /></Route><Show when="signed-in"><Route component={AuthenticatedRoutes} /></Show><Show when="signed-out"><Route component={Home} /></Show></Switch></QueryClientProvider></ClerkProvider>;
}

function RoutedErrorBoundary({ children }: { children: ReactNode }) {
  const [location] = useLocation();
  return <ErrorBoundary resetKey={location}>{children}</ErrorBoundary>;
}

function App() {
  return (
    <TooltipProvider><WouterRouter base={basePath}><ClerkRoutes /></WouterRouter><Toaster /></TooltipProvider>
  );
}

export default App;
