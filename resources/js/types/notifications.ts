import type { RiskLevel, RiskReason } from './packages';

/**
 * Each kind of notification with its own values, matching params() of its class in app/Notifications.
 * To add a kind, add it here and give it a message in components/notifications/notificationMessages.ts.
 */
export type NotificationKind =
    | PackageNotificationKind
    | ProjectNotificationKind;

/**
 * The kinds about one package (PackageNotification): they carry the package's number and title.
 */
export type PackageNotificationKind =
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

/**
 * The kinds about a whole project (ProjectNotification): opening one goes to the project's package list.
 */
export type ProjectNotificationKind = {
    kind: 'risk_changes_summary';
    // How many packages one check changed, and how many of them became each level.
    params: { changed: number; red: number; amber: number; none: number };
};

type InAppNotificationBase = {
    id: string;
    projectName: string;
    isRead: boolean;
    createdAt: string;
};

export type PackageInAppNotification = InAppNotificationBase &
    PackageNotificationKind & {
        packageNumber: string;
        packageTitle: string;
    };

export type InAppNotification =
    | PackageInAppNotification
    | (InAppNotificationBase &
          ProjectNotificationKind & {
              packageNumber: null;
              packageTitle: null;
          });
