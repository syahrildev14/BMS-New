import { AppLayout } from "@/layouts/AppLayout";
import { DashboardPage } from "@/features/dashboard/DashboardPage";
import type { ReactElement } from "react";

export default function Dashboard() {
    return <DashboardPage />;
}

Dashboard.layout = (page: ReactElement) => (
    <AppLayout>{page}</AppLayout>
);