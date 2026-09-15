// Windows shell property store: associates our own Start menu shortcut with toast notifications.
// No COM activation server is needed: notification activation uses prevalerta://open.
using System;
using System.Runtime.InteropServices;

public static class PrevAgendaShortcut
{
    [StructLayout(LayoutKind.Sequential, Pack = 4)]
    struct PropertyKey { public Guid Format; public uint Id; }

    [StructLayout(LayoutKind.Explicit, Size = 24)]
    struct PropVariant
    {
        [FieldOffset(0)] public ushort Type;
        [FieldOffset(8)] public IntPtr Pointer;
    }

    [ComImport, Guid("886D8EEB-8CF2-4446-8D02-CDBA1DBDCF99"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
    interface IPropertyStore
    {
        void GetCount(out uint count);
        void GetAt(uint index, out PropertyKey key);
        void GetValue(ref PropertyKey key, out PropVariant value);
        void SetValue(ref PropertyKey key, ref PropVariant value);
        void Commit();
    }

    [DllImport("shell32.dll", CharSet = CharSet.Unicode, PreserveSig = false)]
    static extern void SHGetPropertyStoreFromParsingName(string path, IntPtr context, uint flags,
        ref Guid iid, [MarshalAs(UnmanagedType.Interface)] out IPropertyStore store);

    public static void Register(string path, string appId)
    {
        Guid iid = typeof(IPropertyStore).GUID;
        IPropertyStore store;
        SHGetPropertyStoreFromParsingName(path, IntPtr.Zero, 2, ref iid, out store);
        try
        {
            var key = new PropertyKey { Format = new Guid("9F4C2855-9F79-4B39-A8D0-E1D42DE1D5F3"), Id = 5 };
            var value = new PropVariant { Type = 31, Pointer = Marshal.StringToCoTaskMemUni(appId) };
            try { store.SetValue(ref key, ref value); }
            finally { Marshal.FreeCoTaskMem(value.Pointer); }

            // A stable stub CLSID keeps the toast in Notification Center after PowerShell exits.
            // The toast and its button must both use activationType="protocol".
            key.Id = 26;
            value = new PropVariant { Type = 72, Pointer = Marshal.AllocCoTaskMem(16) };
            try
            {
                Marshal.StructureToPtr(new Guid("0F721F7D-0719-4972-A58D-A0A117857BA3"), value.Pointer, false);
                store.SetValue(ref key, ref value);
            }
            finally { Marshal.FreeCoTaskMem(value.Pointer); }
            store.Commit();
        }
        finally { Marshal.ReleaseComObject(store); }
    }
}
