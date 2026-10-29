module.exports = ({ config }) => ({
  ...config,
  extra: {
    ...config.extra,
    apiUrl: process.env.EXPO_PUBLIC_API_URL || '',
    eas: {
      ...(config.extra?.eas || {}),
      projectId: process.env.EAS_PROJECT_ID || config.extra?.eas?.projectId || '',
    },
  },
});
