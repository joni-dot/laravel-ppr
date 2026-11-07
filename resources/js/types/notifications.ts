import type { RiskLevel, RiskReason } from './packages';

/**
 * Each kind of notification with its own values, matching params() of its class in app/Notifications.
 * To add a kind, add it here and give it a message in components/notifications/notificationMessages.ts.
 */
export type NotificationKind =
    | {
          kind: 'package_assigned';
          params: { role: 'owner' | 'buyer' };
      }
    | {
          kind: 'date_coming_up';
          // date is "YYYY-MM-DD"; daysLeft counts from the day it was sent.
          params: { milestone: string; date: string; days_left: number };
      }
    | {
          kind: 'date_overdue';
          params: { milestone: string; date: string };
      }
    | {
          kind: 'risk_changed';
          // The reasons and their details as they were when the level changed.
          params: {
              from: RiskLevel;
              to: RiskLevel;
              reasons: RiskReason[];
              float_days: number | null;
              overdue_milestone: string | null;
              overdue_date: string | null;
          };
      };

export type InAppNotification = NotificationKind & {
    id: string;
    projectName: string;
    packageNumber: string;
    packageTitle: string;
    isRead: boolean;
    createdAt: string;
};
