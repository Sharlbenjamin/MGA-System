import * as BackgroundFetch from 'expo-background-fetch';
import * as TaskManager from 'expo-task-manager';
import { backgroundPoll } from './alerts';

export const ALERT_TASK = 'mga-ops-alerts';

TaskManager.defineTask(ALERT_TASK, async () => {
  try {
    const found = await backgroundPoll();
    return found
      ? BackgroundFetch.BackgroundFetchResult.NewData
      : BackgroundFetch.BackgroundFetchResult.NoData;
  } catch {
    return BackgroundFetch.BackgroundFetchResult.Failed;
  }
});

export async function registerBackgroundAlerts() {
  const status = await BackgroundFetch.getStatusAsync();
  if (status === BackgroundFetch.BackgroundFetchStatus.Restricted || status === BackgroundFetch.BackgroundFetchStatus.Denied) {
    return;
  }
  const registered = await TaskManager.isTaskRegisteredAsync(ALERT_TASK);
  if (registered) return;
  try {
    await BackgroundFetch.registerTaskAsync(ALERT_TASK, {
      minimumInterval: 15 * 60,
      stopOnTerminate: false,
      startOnBoot: true,
    });
  } catch {
    // Background fetch is best-effort; lock-screen delivery depends on remote push.
  }
}
