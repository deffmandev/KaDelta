USE [Ka]
GO
/****** Object:  User [BaseKa]    Script Date: 17/06/2025 01:33:42 ******/
CREATE USER [BaseKa] FOR LOGIN [BaseKa] WITH DEFAULT_SCHEMA=[dbo]
GO
ALTER ROLE [db_ddladmin] ADD MEMBER [BaseKa]
GO
ALTER ROLE [db_datareader] ADD MEMBER [BaseKa]
GO
ALTER ROLE [db_datawriter] ADD MEMBER [BaseKa]
GO
/****** Object:  Table [dbo].[DefModBus]    Script Date: 17/06/2025 01:33:42 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[DefModBus](
	[Id] [bigint] IDENTITY(1,1) NOT NULL,
	[Nom] [text] NULL,
	[Addresse] [text] NULL,
	[Port] [nchar](10) NULL,
	[Com] [text] NULL,
 CONSTRAINT [PK_DefModBus] PRIMARY KEY CLUSTERED 
(
	[Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[DefUnites]    Script Date: 17/06/2025 01:33:43 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[DefUnites](
	[Id] [bigint] IDENTITY(1,1) NOT NULL,
	[SF] [int] NULL,
	[Gr] [int] NULL,
	[ModbusId] [int] NULL,
	[Device] [int] NULL,
	[Name] [text] NULL,
	[Type_OnOff] [int] NULL,
	[OnOff] [int] NULL,
	[Type_Alarm] [int] NULL,
	[Alarm] [int] NULL,
	[Type_Mode] [int] NULL,
	[Mode] [int] NULL,
	[Type_Fan] [int] NULL,
	[Fan] [int] NULL,
	[Type_Room] [int] NULL,
	[Room] [int] NULL,
	[Type_SetRoom] [int] NULL,
	[SetRoom] [int] NULL,
	[Type_CodeErreur] [int] NULL,
	[CodeErreur] [int] NULL,
	[LimiteClimH] [int] NULL,
	[LimiteClimB] [int] NULL,
	[LimiteChaudH] [int] NULL,
	[LimiteChaudB] [int] NULL,
	[Prog] [int] NULL,
	[Com] [text] NULL,
 CONSTRAINT [PK_DefUnites] PRIMARY KEY CLUSTERED 
(
	[Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Groupe]    Script Date: 17/06/2025 01:33:43 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Groupe](
	[Id] [bigint] IDENTITY(1,1) NOT NULL,
	[Groupe] [text] NULL,
 CONSTRAINT [PK_Groupe] PRIMARY KEY CLUSTERED 
(
	[Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ProgNom]    Script Date: 17/06/2025 01:33:43 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ProgNom](
	[Id] [bigint] IDENTITY(1,1) NOT NULL,
	[Nom] [text] NULL,
	[Com] [text] NULL,
 CONSTRAINT [PK_ProgNom] PRIMARY KEY CLUSTERED 
(
	[Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ValUnites]    Script Date: 17/06/2025 01:33:43 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ValUnites](
	[Id_Unite] [bigint] NULL,
	[OnOff] [int] NULL,
	[Alarm] [int] NULL,
	[Mode] [int] NULL,
	[Fan] [int] NULL,
	[Room] [int] NULL,
	[SetRoom] [int] NULL,
	[CodeErreur] [int] NULL
) ON [PRIMARY]
GO
