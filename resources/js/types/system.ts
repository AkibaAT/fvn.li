export interface GameStats {
    total: number;
    visible: number;
    latest_update: string;
}

export interface MonthlyTrendData {
    month: string;
    count: number;
}

export interface RatingStats {
    total: number;
    reviews: {
        total: number;
        review_rate: number;
    };
    average_rating: number;
    visible_games: {
        total: number;
        reviews: number;
        review_rate: number;
        average_rating: number;
    };
    latest: string;
    monthly_trend: MonthlyTrendData[];
    visible_games_monthly_trend: MonthlyTrendData[];
}

export interface HealthSummary {
    total: number;
    active: number;
    failed: number;
    never_run: number;
    monitored_on_oh_dear: number;
}

export interface MonitoredTaskLog {
    meta: {
        failure_message?: string;
    };
}

export interface MonitoredTask {
    name: string;
    type: string;
    schedule: string;
    timezone: string;
    last_started: string;
    last_finished: string;
    last_failed: string;
    next_run: string;
    grace_time: number;
    runs_on_one_server: boolean;
    runs_in_maintenance: boolean;
    registered_on_oh_dear: boolean;
    grace_time_in_minutes: number;
    latest_log?: MonitoredTaskLog;
    latest_ping?: string;
    last_skipped?: string;
    last_pinged?: string;
}
