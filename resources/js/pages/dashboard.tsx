import { AppLayout } from "@/layouts/AppLayout";
import { DashboardPage } from "@/features/dashboard/DashboardPage";
import type { ReactElement } from "react";
import type { DashboardWidgets } from "@/shared/types/prd.type";

export default function Dashboard({ widgets }: { widgets: DashboardWidgets }) {
    return <DashboardPage widgets={widgets} />;
}

Dashboard.layout = (page: ReactElement) => (
    <AppLayout>{page}</AppLayout>
);