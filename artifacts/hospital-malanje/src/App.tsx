import { type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
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
import {
  Route,
  Switch,
  Router as WouterRouter,
  useLocation,
} from 'wouter';

const queryClient = new QueryClient();

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

function RoutedErrorBoundary({ children }: { children: ReactNode }) {
  const [location] = useLocation();
  return <ErrorBoundary resetKey={location}>{children}</ErrorBoundary>;
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <WouterRouter base={import.meta.env.BASE_URL.replace(/\/$/, '')}>
          <Router />
        </WouterRouter>
        <Toaster />
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
